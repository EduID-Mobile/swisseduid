<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Swiss edu-ID authentication plugin.
 *
 * @package   auth_swisseduid
 * @copyright 2017 Christian Glahn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    // It must be included from a Moodle page.
}
require_once(__DIR__ . '/vendor/autoload.php');

require_once(__DIR__ . '/lib.php');

require_once($CFG->libdir.'/authlib.php');
require_once(__DIR__ . '/lib/OAuthManager.php');

class auth_plugin_swisseduid extends auth_plugin_base {

    // - overview/config
    // - azp
    // - key
    // - attribute mapping
    private $perspective = "";
    private $manager;

    /**
     * Constructor.
     */
    function auth_plugin_swisseduid() {
        $this->authtype = 'swisseduid';
        $this->config = get_config('auth/swisseduid');
        $this->manager = new OAuthManager();
    }

    function user_login($username, $password) {
        $manual_auth = get_auth_plugin('manual');
        return $manual_auth->user_login($username, $password);
    }

    /**
     * Rules for the plugins
     *
     * @return bool
     */
    function can_signup() { return false; }
    function can_edit_profile() { return false; }
    function can_confirm() { return false; }
    function can_be_manually_set() { return false; }

    // chooses the configuration view to be shown
    public function validate_form($config, &$err) {
        $this->perspective = "config";
        $config = (array) $config;

        if (array_key_exists("azp", $config)) {
            $this->perspective = "azp";
            $this->manager = new OAuthManager($config["azp"]);

            if (array_key_exists("keyid", $config) && !array_key_exists("storekey", $config)) {
                $this->perspective = "key";
            }

            if (array_key_exists("show_mapping", $config) &&
                $config["show_mapping"]) {
                $this->perspective = "mapping";
            }
        }
    }

    /**
     * Prints a form for configuring this authentication plugin.
     *
     * This function is called from admin/auth.php, and outputs a full page with
     * a form for configuring this plugin.
     *
     * @param array $page An object containing all the data for this page.
     */
    function config_form($config, $err, $user_fields) {
        global $CFG, $DB;

        if (!isset($config)) {
            $config = (object) $_GET;
        }

        $authorities = $DB->get_records('auth_oauth_azp');
        $azpurl = "$CFG->wwwroot/auth/swisseduid/azp.php?auth=oauth2&azp=";
        $redirecturl = "$CFG->wwwroot/auth/swisseduid/cb.php";
        $preloginurl = "$CFG->wwwroot/auth/swisseduid/cb.php?";

        // load perspective
        $file = $this->perspective . "_config.php";

        switch($this->perspective) {
            case "azp":
                $azpInfo = $this->manager->get();
                $keyList = $this->manager->getKeys();
                break;
            case "key":
                $azpInfo = $this->manager->get();
                $keyInfo = $this->manager->getKey($config->keyid);
                break;
            case "mapping":
                $azpInfo = $this->manager->get();
                if (!($azpMapping = $this->manager->getMapping())) {
                    $azpMapping = new \stdClass();
                }
                break;
            default:
                $file = "config.php";
                if ($this->manager) {
                    $authorities = $this->manager->getAuthorities();
                    try {
                        $PK = $this->manager->getPrivateKey();
                        $pubKey = $this->manager->getPublicJWK();
                    }
                    catch (Exception $err) {
                        $PK = null;
                    }
                }
                break;
        }

        include __DIR__ . "/views/" . $file;
    }

    /**
     * Processes and stores configuration data for this authentication plugin.
     *
     * @param object $config Configuration object
     */
    function process_config($config) {
        $config = (array) $config;

        if (has_key($config, "storekey")) {
            $this->manager->storeKey($config);
            //return false;
        }
        if (has_key($config, "storemap")) {
            // handle mapping

            $this->manager->storeMapping($config);
            //return false;
        }
        if (has_key($config, "url")) {
            $this->manager->storeAuthority($config);
        }
        if (has_key($config, "pk")) {
            $this->manager->setPrivateKey($config["pk"]);
        }
        if (has_key($config, "gen_key")) {
            $this->manager->generatePrivateKey();
        }

        return false; // moodle never handles the configuration
    }

    function loginpage_idp_list($wantsurl) {
        global $DB, $CFG;

        // load the registered idps create create hook links
        $retval = [];

        $idps = $DB->get_records("auth_oauth_azp");

        $myurl = $CFG->wwwroot . "/auth/swisseduid/cb.php";

        foreach ($idps as $idp) {
            $retval[] = [
                "icon" => new pix_icon("oauth.png", $idp->name),
                "url"  => new moodle_url($myurl,["id" => $idp->id]),
                "name" => $idp->name
            ];
        }
        return $retval;
    }
}
