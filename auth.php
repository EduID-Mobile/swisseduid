<?php


if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}
require_once(__DIR__ . '/vendor/autoload.php');

require_once(__DIR__ . '/lib.php');

require_once($CFG->libdir.'/authlib.php');
require_once(__DIR__ . '/lib/OAuthManager.php');

class auth_plugin_oauth2 extends auth_plugin_base {

    // - overview/config
    // - azp
    // - key
    // - attribute mapping
    private $perspective = "";
    private $manager;

    /**
     * Constructor.
     */
    function auth_plugin_oauth2() {
        $this->authtype = 'oauth2';
		$this->config = get_config('auth/oauth2');
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
        $azpurl = "$CFG->wwwroot/auth/oauth2/azp.php?auth=oauth2&azp=";
        $tlaurl = "$CFG->wwwroot/local/tla/service.php/identity/oauth2";

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

        include __DIR__ . "/" . $file;
    }

    /**
     * Processes and stores configuration data for this authentication plugin.
     *
     * @param object $config Configuration object
     */
    function process_config($config) {
        $config = (array) $config;

        if (array_key_exists("storekey", $config) && isset($config["storekey"])) {
            $this->manager->storeKey($config);
            //return false;
        }
        if (array_key_exists("storemap", $config) && isset($config["storemap"])) {
            // handle mapping

            $this->manager->storeMapping($config);
            //return false;
        }
        if ((array_key_exists("url", $config) && !empty($config["url"]))) {
            $this->manager->storeAuthority($config);
        }
        if (array_key_exists("pk", $config) && !empty($config["pk"])) {
            $this->manager->setPrivateKey($config["pk"]);
        }

		return false; // moodle never handles the configuration
    }

    function loginpage_idp_list($wantsurl) {
        global $DB, $CFG;

        // load the registered idps create create hook links
        $retval = [];

        $idps = $DB->get_records("auth_oauth_azp");

        $myurl = $CFG->wwwroot . "/auth/oauth2/cb.php";

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
