<?php

require_once("$CFG->dirroot/tag/lib.php");
require_once("$CFG->dirroot/user/editlib.php");
require_once("$CFG->dirroot/user/profile/lib.php");
require_once("$CFG->dirroot/user/lib.php");

require_once("$CFG->dirroot/webservice/lib.php");

require_once(__DIR__ . "/OAuthManager.php");

/**
 * Trait for the PowerTLA service model
 *
 * This trait injects plugin specific functions to the service model.
 * This allows updating the plugin specific logic independently from the
 * service models in PowerTLA.
 *
 * NOTE: it utilises the plugin's authoziation manager (OAuthManager) to
 *       minimize code duplication between configuration and runtime
 */
trait OAuthPlugin {
    private $plugin;
    private $wsmgr;
    private $service;
    private $oaMgr;

    protected function findTargetAuthority($azpUrl) {
        if (!$this->oaMgr) {
            $this->oaMgr = new OAuthManager();
        }
        $this->oaMgr->findByUrl($azpUrl);
        $object = $this->oaMgr->get();

        if (!$object) {
            throw new \RESTling\Exception\Forbidden();
        }
        return (array) $object;
    }

    /**
 	 * verifies, if the plugin is active *and* if the services are exposed.
 	 *
 	 * @return boolean
	 */
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

    /**
 	 * loads the attribute map for the active authority.
     * The attribute map links Moodle's user attributes with the
     * assertion claims of the authorization service.
     *
     * If the authorization service has no attribute map configured, this
     * function will return the default attribute mapping.
 	 *
 	 * @return array $attributeMap
	 */
	protected function getAttributeMap() {
        $map = $this->oaMgr->getMapping();

        if (empty($map)) {
            $map = $this->oaMgr->getDefaultMapping();
        }

        return $map;
    }


    /**
 	 * deletes a token for a given field.
     * used by the revocation logic.
 	 *
 	 * @param string $field - either 'access_token' or 'refresh_token')
     * @param string $token - the presented token
 	 * @return void
	 */
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

    /**
 	 * Returns the private key for the moodle service
 	 *
 	 * @param type
 	 * @return void
	 */
	protected function getPrivateKey() {
        return $this->oaMgr->getPrivateKey()->crypt_key;
    }

    /**
 	 * loads a shared key for the given attributes
     *
     * used for finding decryption and signing keys.
 	 *
 	 * @param array $keyAttributes
 	 * @return object - the key object
	 */
	protected function getKey($attr) {
        global $DB;
        $object = $DB->get_record("auth_oauth_keys", $attr);
        if (!$object) {
            throw new \RESTling\Exception\Forbidden();
        }
        return $object;
    }

    /**
 	 * Verfies if the presented issuer string matches the presented authority
 	 *
     * If the issuer is not verified this function throws a Forbidden
     * exception.
     *
 	 * @param string $iss - the iss claim
     * @param id $id - the id of the authority
 	 * @return void
	 */
	protected function verifyIssuer($iss, $id) {
        global $DB;
        $this->oaMgr = new OAuthManager($id);
        $object = $this->oaMgr->get();
        if (!$object) {
            throw new \RESTling\Exception\Forbidden();
        }
        if ($object->iss != $iss) {
            throw new \RESTling\Exception\Forbidden();
        }
    }

    /**
 	 * stores the request state for the presented attributes
 	 *
 	 * @param string $state - the state string as passed to the authorization service
     * @param array $stateAttributes - informs, which attributes are stored
 	 * @return void
	 */
	protected function storeState($state, $attr) {
        $attr["id"] = $state;
        $DB->insert_record("auth_oauth_state", $attr);
    }

    /**
 	 * loads the state attributes for a given state string.
 	 *
 	 * @param string $state - the state string as used by the authorization service
 	 * @return void
	 */
	protected function loadState($state) {
        // loads the state Object
        global $DB;

        $stateObj = $DB->get_record("auth_oauth_state", ["id" => $state]);
        if (!$stateObj) {
            throw new \RESTling\Exception\Forbidden();
        }

        return (array)$stateObj;
    }

    /**
 	 * generates and inserts a token for the preseted attributes
 	 *
 	 * @param array $attributes
     * @param int $expires - expiration timestamp, until when the token is valid
 	 * @return void
	 */
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

    /**
 	 * fetches the token information for the presented token
 	 *
 	 * @param string $field - either 'access_token' or 'refresh_token'
     * @param string $token - the presented token
 	 * @return void
	 */
	protected function getToken($field, $token) {
        global $DB;

        $attrMap = [];
        $attrMap[$field] = $token;
        if($recToken = $DB->get_record("auth_oauth_tokens", $attrMap)) {
            return (array)$recToken;
        }

        $attrMap = [];
        $attrMap["initial_$field"] = $token;
        if($recToken = $DB->get_record("auth_oauth_tokens", $attrMap)) {
            return (array)$recToken;
        }
        throw new \RESTling\Exception\Forbidden();
    }

    /**
 	 * stores a new token.
     * This function automatically decides whether to create or update
     * the token information based on the present state of the request
 	 *
 	 * @param string $access_token
     * @param string $refresh_token
     * @param int $ttl - seconds from now until the token expires.
 	 * @return void
	 */
	protected function storeToken($aT, $rT, $ttl) {
        global $DB;
        global $USER;

        $ts = time();
        $ex = $ts + $ttl;
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

    /**
 	 * returns the tokenUrl for a given state.
     *
     * This is used for the Code Flow.
 	 *
 	 * @param string $state
 	 * @return string $tokenUrl
	 */
	protected function getTokenUrl($state) {
        global $DB;

        $stateObj = $DB->get_record("auth_oauth_state", ["id" => $state]);
        if (!$stateObj) {
            throw new \RESTling\Exception\Forbidden();
        }

        $azp = $DB->get_record("auth_oauth_azp", ["id" => $stateObj->azp_id]);
        if (!$azp) {
            throw new \RESTling\Exception\Forbidden();
        }

        return $azp->url . "/token";
    }
}

?>
