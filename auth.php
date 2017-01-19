<?php

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

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
            $changes = [];
            foreach (["kid", "jku", "crypt_key", "azp", "keyid"] as $attr) {
                if (array_key_exists($attr, $config) && !empty($config[$attr])) {
                    $changes[$attr] = $config[$attr];
                }
            }

            $this->manager->storeKey($changes);
            //return false;
        }
        elseif (array_key_exists("storemap", $config) && isset($config["storemap"])) {
            // handle mapping
            $mAttr = $this->getUserAttributes();
            $map = [];
            foreach ($mAttr as $attr) {
                $map[$attr] = $config[$attr];
            }
            $this->manager->store(["attr_map" => json_encode($map)]);
            //return false;
        }
        elseif ((array_key_exists("storeazp", $config) && isset($config["storeazp"])) || (array_key_exists("client_id", $config) && !empty($config["client_id"]))) {
            $changes = [];
            foreach (["name", "url", "client_id", "flow", "auth_type", "credentials", "iss"] as $attr) {
                if (array_key_exists($attr, $config) && !empty($config[$attr])) {
                    $changes[$attr] = $config[$attr];
                }
            }

            $this->manager->store($changes);
            //return false;
        }
        elseif (array_key_exists("pk", $config) && !empty($conifg["pk"])) {
            $this->manager->setPrivateKey($config["pk"]);
            //return false;
        }

		return false; // moodle never handles the configuration
    }

    function getUserAttributes() {
        return array_keys($this->getDefaultMapping());
    }

    function getStandardClaims() {
        return [
            "email", "given_name", "family_name", "middle_name", "nickname",
            "preferred_username", "profile", "picture", "website",
            "email_verified", "gender", "birthdate", "zoneinfo", "locale",
            "phone_number", "phone_number_verified", "updated_at",
            "address.street_address", "address.city", "address.locality",
            "address.country", "address.postal_code", "address.region"
        ];
    }

    function loginpage_idp_list($wantsurl) {
        global $DB, $CFG;

        // load the registered idps create create hook links
        $retval = [];

        $idps = $DB->get_records("auth_oauth_azp");

        $myurl = $CFG->wwwroot . "/local/tla/service.php/identity/oauth2/id";

        foreach ($idps as $idp) {
            $retval[] = [
                "icon" => pix_icon("oauth.png", $idp->name, $component),
                "url"  => moodle_url($myurl,["idp" => $idp->id]),
                "name" => $idp->name
            ];
        }
        return $retval;
    }
}
