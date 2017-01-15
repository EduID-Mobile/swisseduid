<?php

require_once("$CFG->dirroot/tag/lib.php");
require_once("$CFG->dirroot/user/editlib.php");
require_once("$CFG->dirroot/user/profile/lib.php");
require_once("$CFG->dirroot/user/lib.php");

require_once("$CFG->dirroot/webservice/lib.php");

// this creates a trait for the PowerTLA service model
//
// This trait injects plugin specific functions to the service model.
// This allows updating the plugin specific logic independently from the
// service models in PowerTLA

trait OAuthPlugin {
    private $plugin;
    private $wsmgr;
    private $service;

    protected function inactive() {
        // moodle is loaded at this point

        // Check if the oauth plugin is active
        if (!$this->plugin) {
            $authsequence = get_enabled_auth_plugins(true); // auths, in sequence
            foreach($authsequence as $authname) {
                if (strstr("oauth") === 'oauth') {
                    $this->plugin = get_auth_plugin($authname);
                    return false;
                }
            }
        }

        // check if the web service is present and active
        if (!$this->wsmgr) {
            $this->wsmgr = new webservice();
        }

        // we may use the TLA (intentity services)
        $service = $this->wsmgr->get_external_service_by_shortname("OAuth2", IGNORE_MISSING);
        if (!$service) {
            // or alternatively the OAuth2 services.
            $service = $this->wsmgr->get_external_service_by_shortname("TLA", IGNORE_MISSING);
        }

        if (!$service) {
            return false;
        }

        $this->service = $service;

        return true;
    }

    protected function handleUser($userClaims) {
        global $DB, $USER;

        // create or update the user
        $username = $userClaims["sub"];
        if ($user = $DB->get_record("user", ["username" => $sub])) {
            // update a user
            $user = $this->handleAttributeMap($user, $userClaims);
            user_update_user($user, false, false);

            $USER = $DB->get_record('user', array('id' => $user['id']));
        }
        else {
            // create a new user
            $user = $this->handleAttributeMap([], $userClaims);
            $user['id'] = user_create_user($user, false, false);
            if ($user['id'] > 0)
            {
                // moodle wants additional profile setups
                $usercontext = context_user::instance($user['id']);

                // Update preferences.
                useredit_update_user_preference($user);

                if (!empty($CFG->usetags)) {
                    useredit_update_interests($user, $user['interests']);
                }

                // Update mail bounces.
                useredit_update_bounces($user, $user);

                // Update forum track preference.
                useredit_update_trackforums($user, $user);

                // Save custom profile fields data.
                profile_save_data($user);

                // Reload from db.
                $usernew = $DB->get_record('user', array('id' => $user['id']));

                // allow Moodle components to respond to the new user.
                core\event\user_created::create_from_userid($usernew->id)->trigger();
            }
        }

        return $user["id"];
    }

    protected function grantInternalToken($userid, $expires) {
        return external_generate_token(EXTERNAL_TOKEN_PERMANENT,
                                       $this->service->id,
                                       $userid,
                                       context_system::instance(),
                                       $expires,
                                       '');
    }

    protected function startUserSession() {
        global $USER;
        \core\session\manager::login_user($USER);
    }

    protected function getAttributeMap() {
        // TODO implement map loading
        return [];
    }

    protected function deleteToken($field, $token) {
        // we simply forget about our tokens
        global $DB;

        $attrMap = [];
        $attrMap[$field] = $token;
        $DB->delete_records("auth_oauth_tokens", $attrMap);

        $attrMap = [];
        $attrMap["initial_$field"] = $token;
        $DB->delete_records("auth_oauth_tokens", $attrMap);
    }

    protected function getKey($attr) {
        global $DB;
        $object = $DB->get_record("auth_oauth_keys", $attr);
        if (!$object) {
            throw new \RESTling\Exception\Forbidden();
        }
        return $object;
    }

    protected function verifyIssuer($iss, $id) {
        global $DB;
        $object = $DB->get_record("auth_oauth_azp", ["id" => $kid]);
        if (!$object) {
            throw new \RESTling\Exception\Forbidden();
        }
        if ($object->id != $id) {
            throw new \RESTling\Exception\Forbidden();
        }
    }

    protected function storeState($state, $attr) {
        $attr["id"] = $state;
        $DB->insert_record("auth_oauth_state", $attr);
    }

    protected function loadState($state) {
        // loads the state Object
        global $DB;

        $stateObj = $DB->get_record("auth_oauth_state", ["id" => $state]);
        if (!$stateObj) {
            throw new \RESTling\Exception\Forbidden();
        }

        return (array)$stateObj;
    }

    protected function generateToken($attr, $expires = 0) {
        global $DB;
        $ts = time();

        if ($expires) {
            $ex = $expires - $ts;
        }
        else {
            $ex= 86000;
            $expires = $ts + $ex;
        }

        if (!array_key_exists("access_token", $attr)) {
            $attr["access_token"] = $this->randomString(40);
        }

        $attr["refresh_token"] = $this->randomString(40);
        $attr["expries"] = $ex;
        $attr["created"] = $ts;

        $attr["initial_access_token"]  = $attr["access_token"];
        $attr["initial_refresh_token"] = $attr["refresh_token"];

        $DB->insert_record("auth_oauth_tokens", $attr);

        return [$attr["access_token"], $attr["refresh_token"], $expires];
    }


    protected function storeToken($aT, $rT, $ex) {
        global $DB;
        global $USER;

        $ts = time();
        $ex = $ts + $ex;
        if (!empty($this->stateInfo)) { // avoid random errors
            $azpId  = $this->stateInfo["azp_id"];
            $tokenId = $this->stateInfo["token_id"];
            $updateId = $this->stateInfo["refresh_id"];
        }

        $attr = [
            "access_token" => $aT,
            "refresh_token" => $rT,
            "expries" => $ex
        ];

        if (empty($updateId)) {
            // new token
            $attr["initial_access_token"] = $aT;
            $attr["initial_refresh_token"] = $rT;
            $attr["created"] = $ts;
            $attr["azp_id"] = $azpId;
            $attr["userid"] = $USER->id;

            if (!empty($tokenId)) {
                $attr["parent"] = $tokenId;
            }
            $DB->insert_record("auth_oauth_tokens", $attr);
        }
        else {
            // refresh token
            $attr["id"] = $updateId;
            $DB->update_record("auth_oauth_tokens", $attr);
        }
    }

    protected function getTokenUrl($state) {
        return null;
    }
}

?>
