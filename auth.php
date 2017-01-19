<?php

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->libdir.'/authlib.php');
require_once(__DIR__ . '/lib/OAuthManager.php');

class auth_plugin_eduid extends auth_plugin_base {

    // - overview/config
    // - azp
    // - key
    // - attribute mapping
    private $perspective = "";
    private $manager;

    /**
     * Constructor.
     */
    function auth_plugin_eduid() {
        $this->authtype = 'eduid';
		$this->config = get_config('auth/eduid');
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
    public function validate_form($config, $err) {
        $this->perspective = "config";
        if (array_key_exists("azp", $config)) {
            $this->perspective = "azp";
            $this->manager = new OAuthManager($config["azp"]);

            if (array_key_exists("keyid", $config)) {
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
		$authorities = $DB->get_records('auth_oauth_azp');
        $azpurl = "$CFG->wwwroot/$CFG->admin/apz.php?auth=$auth&azp=";
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
                $keyInfo = $this->manager->getKey($config["keyid"]);
                break;
            case "mapping":
                $azpInfo = $this->manager->get();
                if (!($azpMapping = $this->manager->getMapping())) {
                    $azpMapping = new \stdClass();
                }
                break;
            default:
                $file = "config.php";
                $PK = $this->getPrivateKey();
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
        if (array_key_exists("storekey", $config) && isset($config["storekey"])) {
            $changes = [];
            foreach (["kid", "jku", "key", "azp", "keyid"] as $attr) {
                if (array_key_exists($attr, $config) && !empty($config[$attr])) {
                    $changes[$attr] = $config[$attr];
                }
            }

            $this->manager->storeKey($attr);
            //return false;
        }
        elseif (array_key_exists("storemap", $config) && isset($config["storemap"])) {
            // handle mapping
            $mAttr = $this->getUserAttributes();
            $map = [];
            foreach ($mAttr as $attr) {
                $map[$attr] = $config[$attr];
            }
            $this->manager->store(["attrMap" => json_encode($map)]);
            //return false;
        }
        elseif (array_key_exists("storeazp", $config) && isset($config["storeazp"])) {
            $changes = [];
            foreach (["name", "url", "client_id", "flow", "auth_type", "credentials", "iss"] as $attr) {
                if (array_key_exists($attr, $config) && !empty($config[$attr])) {
                    $changes[$attr] = $config[$attr];
                }
            }

            $this->manager->store($changes);
            //return false;
        }
        elseif (array_key_exists("storepk", $config)) {
            if (array_key_exists("pk", $config)) {
                $this->manager->setPrivateKey($config["pk"]);
            }
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

	function addAuthorityEntries($entries) {
        global $CFG, $DB;
		// add new entries
		foreach($entries as $num => $entry) {
			if(
				empty($entry["authority_name"]) ||
				empty($entry["authority_url"]) ||
                empty($entry["client_id"]) ||
				empty($entry["authority_shared_token"]) ||
				empty($entry["privkey_for_authority"]) ||
				empty($entry["authority_public_key"])
			) {
				print_r('ignoring the following');
				print_r($entry);
				continue; // ignore incomplete entries
			} else {
                // INSERT OR UPDATE?
				$DB->insert_record('auth_oauth_azp',
					array(
						"name" => $entry["authority_name"],
						"url" => $entry["authority_url"],
						"client_id" => $entry["client_id"],
                        "flow" => "hybrid",
                        "auth_type" => "shibboleth"
                    )
                );

                // get the id
                $azp = $DB->get_record('auth_oauth_azp', ['
                    url' => $entry['authority_url']
                ]);

                // Keys are handled differently
                $azp_id = $azp->id;

                // insert the local private key
                $kid = 'private';
                $key = $entry["privkey_for_authority"];
                $DB->insert_record('auth_oauth_keys',[
                    'azp_id' => $azp_id,
                    'kid' => $kid,
                    'key' => $key
                ]);

                // insert the authorization public key
                $kid = $azp->url;
                $key = $entry["authority_public_key"];
                $DB->insert_record('auth_oauth_keys', [
                    'azp_id' => $azp_id,
                    'kid' => $kid,
                    'key' => $key
                ]);
			}
		}
		return true;
	}

	function updateAuthorityEntries($entries) {
        global $CFG, $DB;
		// purge the deleted entries
		$current_authority_entries = $DB->get_records('auth_eduid_authorities');
		foreach($current_authority_entries as $authority) {
			if(!array_key_exists($authority->id, $entries)) {
				$DB->delete_records('auth_eduid_authorities', array('id' => $authority->id));
			}
		}
		// update the rest
		$current_authority_entries = $DB->get_records('auth_eduid_authorities');
		foreach($current_authority_entries as $authority) {
			/* print_r($content); continue; */
			if(
				$authority->authority_name != $entries[$authority->id]["authority_name"] ||
				$authority->authority_url != $entries[$authority->id]["authority_url"] ||
				$authority->authority_shared_token != $entries[$authority->id]["authority_shared_token"] ||
				$authority->privkey_for_authority != $entries[$authority->id]["privkey_for_authority"] ||
				$authority->authority_public_key != $entries[$authority->id]["authority_public_key"]
			) {
				$DB->update_record('auth_eduid_authorities',
					array(
						"id" => $authority->id,
						"authority_name" => $entries[$authority->id]["authority_name"],
						"authority_url" => $entries[$authority->id]["authority_url"],
						"authority_shared_token" => $entries[$authority->id]["authority_shared_token"],
						"privkey_for_authority" => $entries[$authority->id]["privkey_for_authority"],
						"authority_public_key" => $entries[$authority->id]["authority_public_key"]
					)
				);
			}
		}

		return true;
	}

	function create_user_session($userid) {
		// check if the user is valid
		$USER = $DB->get_record('user', array('username' => $_POST['username']) );
		if(empty($USER)) {
			return $this->error(2);
		} else {
			return true;
		}
	}

	function error($code) {
		$error = array(
				'exception' => 'unknown exception',
				'message' => 'no message',
				'debuginfo'=>'code: '.$code
				);

		switch ($code) {
			case 0:
				$error["exception"] = 'authentication_failed';
				$error["message"] = 'Authentication failed. Authorization code not valid.';
				break;
			case 1:
				$error["exception"] = 'grant_type_exception';
				$error["message"] = 'The grant_type is not valid!';
				break;
			case 2:
				$error["exception"] = 'code_exception';
				$error["message"] = 'The code is not valid!';
				break;
			case 3:
				$error["exception"] = 'access_token_exception';
				$error["message"] = 'The access token is not valid!';
				break;
			case 4:
				$error["exception"] = 'service_shortname_exception';
				$error["message"] = 'The service shortname is not valid!';
				break;
			case 5:
				$error["exception"] = 'unknown_user';
				$error["message"] = 'The selected user has never accessed the website';
				break;
			case 6:
				$error["exception"] = 'invalid_request';
				$error["message"] = 'The revoke request is not valid. Please provide the token and token_type_hint parameters. token_type_hint should be access_token or refresh_token.';
				break;
			case 7:
				$error["exception"] = 'revoking_permission_not_valid';
				$error["message"] = 'The current token does not belong to the authenticated user.';
				break;
			case 8:
				$error["exception"] = 'revoking_not_necessary';
				$error["message"] = 'The authenticated user has no tokens to revoke.';
				break;
			default:
				$error["exception"] = 'unknown_error_exception';
				$error["message"] = 'Unknown server error!';
				break;
		}
		return $error;
	}
}
