<?php

require_once("$CFG->dirroot/tag/lib.php");
require_once("$CFG->dirroot/user/editlib.php");
require_once("$CFG->dirroot/user/profile/lib.php");
require_once("$CFG->dirroot/user/lib.php");

require_once("$CFG->dirroot/webservice/lib.php");

require_once __DIR__ . "/OAuthManager.php";

use \Curler\Request as Curler;

class OAuthCallback {
    private $manager;
    private $myuri;

    private $service;
    private $webservice;

    public function __construct() {
        global $CFG;
        $this->manager = new OAuthManager();
        $this->myuri = $CFG->wwwroot . "/auth/oauth2/cb.php";
    }

    public function isActive() {
        $authsequence = get_enabled_auth_plugins(true); // auths, in sequence
        $failed = true;
        foreach($authsequence as $authname) {
            if (strstr($authname, "oauth2") === 'oauth2') {
                $failed = false;
            }
        }

        if ($failed) {
            return false;
        }
        return true;
    }

    public function handleAuthorization() {
        // this can be triggered through the web or through an app
        global $CFG, $SESSION;
        $id = $_GET["id"];      // web
        if ($id > 0) {
            $target = $this->manager->findById($id);
        }

        if (!$target) {
            redirect($CFG->wwwroot);
            exit;
        }

        $urltogo = $CFG->wwwroot;
        if ($SESSION->wantsurl) {
            $urltogo = $SESSION->wantsurl;
            unset($SESSION->wantsurl);
            // the session will be automatically updated
        }

        $state = $this->manager->createState(["redirect_uri" => $urltogo]);

        $opts = [
            "client_id" => $target->client_id,
            "state" => $state,
            "redirect_uri" => $this->myuri,
            "scope" =>"openid profile email"
        ];

        $opts["scope"] = "openid profile email";
        switch ($target->flow) {
            case "implicit":
                $opts["response_type"]= "id_token";
                break;
            case "hybrid":
                $opts["response_type"]= "code id_token token";
                break;
            case "code":
                $opts["response_type"]= "code";
                break;
            default:
                return $CFG->wwwroot;
                break;
        }

        $param = [];
        foreach ($opts as $k => $v) {
            $param[] = urlencode($k)."=".urlencode($v);
        }
        redirect($target->url . "?" . join("&", $param));
    }

    public function authorizeUser() {
        global $CFG;
        $state = $_GET["state"];
        if (!($stateInfo = $this->manager->getState($state))) {
            return;
        }
        $res = $_GET;
        if (!array_key_exists("id_token", $_GET) &&
             array_key_exists("code", $_GET)) {
            $res = $this->loadCodeAuthorization();
        }

        if (!$this->handleToken($res, $stateInfo)) {
            http_response_code(403);
        }
    }

    public function authorizeAssertion() {
        global $CFG;

        if (array_key_exists("aud", $_GET))
        {
            $uri = dirname($_GET["aud"]); // strip the token endpoint
            if (!($target = $this->manager->findByUrl($uri))) {
                http_response_code(403);
                return;
            }

            $param = [];
            foreach ($_GET as $k => $v) {
                $param[$k] = $v;
            }

            $param["scope"] = "openid profile email";

            $res = $this->callTokenEndpoint($uri, $param);

            if (!$this->handleToken($res)) {
                http_response_code(403);
            }
        }
        else {
            http_response_code(400);
        }
    }

    private function loadCodeAuthorization() {
        $target = $this->manager->get();
        $param = [
            "grant_type" => "authorization_code",
            "client_id"  => $target->client_id,
            "redirect_uri" => $this->myuri,
            "code" => $_GET["code"]
        ];
        return $this->callTokenEndpoint($param);
    }

    private function callTokenEndpoint($param) {
        $target = $this->manager->get();
        $curl = new Curler($target->url);
        $curl->setPathInfo("token");
        // we expect formdata
        $p = [];
        foreach ($param as $k => $v) {
            $p[] = urlencode($k) . '=' . urlencode($v);
        }

        $curl->post(join('&', $p), "application/x-www-form-urlencoded");

        if ($curl->getSatus() == 200) {
            $h = $curl->getHeader();
            $ct = $h["content_type"];

            // OIDC returns JSON
            if (strpos($ct, "application/json") !== false) {
                try {
                    $result = json_decode($curl->getBody, true);
                }
                catch (Exception $err) {
                    $result = null;
                }
            }
            // other OAuth2 APs MAY return other formats?
            // parse_str($curl->getBody(), $result);
        }

        if (!$result || array_key_exists("error", $result)) {
            return null;
        }

        return $result;
    }

    private function handleToken($response, $stateInfo=null) {
        if (empty($response)) {
            return false;
        }

        $id_token      = $response["id_token"];
        $access_token  = $response["access_token"];
        $refresh_token = $response["refresh_token"];
        $expires       = $response["expires_in"];

        if (empty($id_token)) {
            return false;
        }

        if (!($user = $this->processAssertion($id_token))) {
            return false;
        }

        if (!($user = $this->handleUser($user))) {
            return false;
        }

        // store the tokens for revocation
        $now = time();
        $exp = $now + $expires;

        if ($stateInfo && $stateInfo->refresh_id > 0) {
            $token = $this->manager->getTokenById($stateInfo->refresh_id);
            if (!$token) {
                return false; // no token
            }
            $token["access_token"]  = $access_token;
            $token["refresh_token"] = $refresh_token;
            $token["expires"]       = $exp;

            // we no longer need the state information
            $this->manager->consumeState($state);
        }
        else {
            $target =  $this->manager->get();
            $token = [
                "access_token"  => $access_token,
                "refresh_token" => $refresh_token,
                "expires"       => $exp,
                "created"       => $now,
                "azp_id"        => $target->azp_id,
                "userid"        => $user->id
            ];
        }

        $this->manager->storeToken($token);

        // we have two cases
        // 1. we authorized a user through the web (with redirect_uri)
        if ($stateInfo && $stateInfo->redirect_uri) {
            // FIXME: we MUST track the session
            // so we can delete the token on logout
            // we also want to terminate the session if the token is revoked
            $this->startWebSession($stateInfo);
            return true;
        }

        // 2. we issued a moodle token for an app
        $moodle_token = $this->grantInternalToken($user->id, $expires);

        $cliToken = [
            "access_token"  => $moodle_token,
            "refresh_token" => $refresh_token,
            "expires_in"    => $expires,
            "token_type"    => "Bearer"
        ];

        echo json_encode($cliToken);
        return true;
    }

    private function startWebSession($stateInfo) {
        // start session and redirect.
        global $CFG, $SESSION, $USER;
        \core\session\manager::login_user($USER);

        $urltogo = $stateInfo->redirect_uri;
        if ($SESSION->wantsurl) {
            $urltogo = $SESSION->wantsurl;
            unset($SESSION->wantsurl);
            // the session will be automatically updated
        }
        redirect($urltogo);
    }

    private function processAssertion($jwt) {
        if (!($jwt = $this->decryptJWE($jwt))) {
            return null;
        }
        if (!($jwt = $this->validateJWT($jwt))) {
            return null;
        }

        $idClaims = self::getSupportedClaims();
        $userClaims = [];
        foreach ($idClaims as $claim) {
            if ($cdata = $jwt->getClaim($claim)) {
                $userClaims[$claim] = $cdata;
            }
        }

        return $userClaims;
    }

    private function decryptJWE($jwt) {
        $pk = $this->manager->getPrivateKey();
        if ($pk && $jwt instanceof \Jose\Object\JWE) {
            $jwk_set = $this->prepareKeySet($pk, ["use"=>"enc"]);

            $alg = $jwt->getSignature(0)->getProtectedHeader('alg');
            $enc = $jwt->getSignature(0)->getProtectedHeader('enc');

            if (empty($alg) && empty($enc)) {
                return null;
            }
            $zip = $jwt->getSignature(0)->getProtectedHeader('zip');
            if (empty($zip)) {
                $zip = ['DEF', 'ZLIB', 'GZ'];
            }
            else {
                $zip = [$zip];
            }

            $decrypter = \Jose\Decrypter::createDecrypter([$alg], [$enc],$zip);

            try {
                $decrypter->decryptUsingKeySet($jwt, $jwk_set, null);
            }
            catch (Exception $err) {
                return null;
            }

            $payload = $jwt->getPayload();
            if (empty($payload)) {
                return null;
            }

            if (is_array($payload) && array_key_exists('signatures', $payload)) {
                $jwt = \Jose\Util\JWSLoader::loadSerializedJsonJWS($payload);
                if (!$jwt || !($jwt instanceof \Jose\Object\JWS)) {
                    return null;
                }
            }
            else {
                return null;
            }
        }
        return $jwt;
    }

    private function validateJWT($jwt) {
        if ($jwt instanceof \Jose\Object\JWS) {
            $kid = $jwt->getSignature(0)->getProtectedHeader('kid');
            $jku = $jwt->getSignature(0)->getProtectedHeader('jku');
            $alg = $jwt->getSignature(0)->getProtectedHeader('alg');

            if (empty($alg)) {
                return null;
            }

            $key = $this->manager->getValidationKey($kid, $jku);
            if (!$key) {
                return null;
            }

            $jwk_set = $this->prepareKeySet($key->crypt_key, ["use"=>"sig"]);
            $verifier = \Jose\Verifier::createVerifier([$alg]);
            try {
                $verifier->verifyWithKeySet($jwt, $jwk_set, null, null);
            }
            catch (Exception $err) {
                return null;
            }

            $azp = $this->manager->get();
            $iss = $jwt->getClaim('iss');

            if (empty($iss) || $iss !== $azp->iss) {
                return null;
            }

            $aud = $jwt->getClaim('aud');
            if (empty($aud) || $aud !== $this->myuri) {
                return null;
            }
        }
        return $jwt;
    }

    private function prepareKeySet($keyString, $keyAttr) {
        $key = \Jose\Factory\JWKFactory::createFromKey($keyString,
                                                       null,
                                                       $keyAttr);
        if (!$key) {
            return null;
        }

        $jwk_set = new \Jose\Object\JWKSet();
        $jwk_set->addKey($key);
        return $jwk_set;
    }

    private function handleUser($userClaims) {
        global $DB, $USER; // NOTE: Moodle, but not plugin specific

        // create or update the user
        $username = $userClaims["sub"];
        if ($user = $DB->get_record("user", ["username" => $username])) {
            // update a user
            $user = $this->handleAttributeMap($user, $userClaims);
            user_update_user($user, false, false);

            $user = $DB->get_record('user', array('id' => $user['id']));
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
                $user = $DB->get_record('user', array('id' => $user['id']));

                // allow Moodle components to respond to the new user.
                core\event\user_created::create_from_userid($usernew->id)->trigger();

            }
        }
        $USER = $user;
        return $user;
    }

    private function handleAttributeMap($user, $claims) {
        global $CFG;

        $user = (array) $user;
        $claims = (array) $claims;

        if (!array_key_exists(["id", $user])) {
            // set the defaults for new users
            $user['timecreated']  = $user['firstaccess'] = $user['lastaccess'] = time();
            $user['confirmed']    = 1;
            $user['policyagreed'] = 1;
            $user['suspended']    = 0;
            $user['mnethostid']   = $CFG->mnet_localhost_id;
            $user['interests']    = '';
            $user['password']     = AUTH_PASSWORD_NOT_CACHED;
        }

        $user['deleted']   = 0; // always reset an account
        $user["username"] = $claims['sub'];

        // get attribute map
        // attrmap -> moodlevalue => claim
        $map = $this->manager->getMapping();

        $didUpdate = false;
        foreach ($map as $mKey => $cKey) {
            $cs = $claims;
            // this trick is needed for handling the address claim
            if (strpos(".", $cKey) !== false) {
                // handle combined claims
                list($pKey, $cKey) = explode(".", $cKey);
                if (array_key_exists($pKey, $cs)) {
                    $cs = $cs[$pKey];
                }
            }

            if (!empty($cs) &&
                !empty($cKey) &&
                array_key_exists($cKey, $cs) &&
                (!array_key_exists($mKey, $user) || $user[$mKey] != $cs[$cKey])) {

                $user[$mKey] = $cs[$cKey];
                $didUpdate = true;
            }
        }

        // authomatically mark the updated time
        if ($didUpdate) {
            $user['timemodified'] = time();
        }
        return $user;
    }

    private function grantInternalToken($userid, $expires) {
        // Internal Tokens from the OAuth perspective are tokens issued by
        // the service, whereas moodle considers tokens that are not used as
        // sessions as external.
        global $DB;
        $service = $DB->get_record('external_services',
                                    ['name'=>'OAuth2'],
                                    '*',
                                    IGNORE_MISSING);

        // one problem here is that the token will not work with the service
        // endpoints.
        // this means that OAuth2 tokens need to be either scoped OR assigned
        // to all service endpoints.
        // scoping means that we need to know upfront, which services the
        // client requests.
        // in the case of multiple scopings, ONE token needs to be assignable to
        // several services. However, Moodle's external tokens don't support
        // this.
        // Eitherway, in moodle there is no way for doing this dynamically. This
        // means that one needs to assign ALL moodle service endpoints to the
        // OAuth "service". A cron job could take all active services and
        // assign all their endpoints to the OAuth2 service.
        // a saner way would be to allow OAuth2's token management to hook into
        // the external token handling and let OAuth2's scoping handle the job.
        if ($service) {
            return external_generate_token(EXTERNAL_TOKEN_PERMANENT,
                                           $service,
                                           $userid,
                                           context_system::instance(),
                                           $expires);
        }
        return null;
    }

    static public function getSupportedClaims() {
        // by default we support all claims
        return explode(" ",
            "sub name given_name family_name middle_name nickname preferred_username profile picture website email email_verified gender brithdate zoneinfo locale phone_number phone_number_verified address updated_at formatted");
    }
}
