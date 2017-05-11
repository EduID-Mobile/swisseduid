<?php

require_once("$CFG->dirroot/tag/lib.php");
require_once("$CFG->dirroot/user/editlib.php");
require_once("$CFG->dirroot/user/profile/lib.php");
require_once("$CFG->dirroot/user/lib.php");

require_once("$CFG->dirroot/webservice/lib.php");

require_once(__DIR__ . "/OAuthManager.php");

use \Curler\Request as Curler;

use \Jose\Loader;
use \Jose\Object\JWE;
use \Jose\Object\JWS;
use \Jose\Object\JWK;
use \Jose\Object\JWKSet;
use \Jose\Decrypter;
use \Jose\Verifier;
use \Jose\Util\JWSLoader;
use \Jose\Factory\JWKFactory;

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
        return (count(pick_keys($authsequence, "oauth2")) > 0);
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
        redirect($target->authorization_endpoint . "?" . join("&", $param));
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

            // we are in code-flow
            $target = $this->manager->get();
            $param = [
                "grant_type"   => "authorization_code",
                "client_id"    => $target->client_id,
                "redirect_uri" => $this->myuri,
                "code"         => $_GET["code"]
            ];
            $res = $this->callTokenEndpoint($param);
        }

        if (!$this->handleToken($res, $stateInfo)) {
            http_response_code(403);
        }
    }

    public function authorizeAssertion() {
        global $CFG;

        $param = array_merge($_GET, ["scope"=> "openid profile email"]);
        $res = $this->callTokenEndpoint($param);

        if (!$this->handleToken($res)) {
            http_response_code(403);
        }
    }

    private function callTokenEndpoint($param) {
        $target = $this->manager->get();

        $curl = new Curler($target->token_endpoint);

        $curl->setCredentials($target->client_id, $target->credentials);
        unset($param["client_id"]); // because the client id is in the header already

        // $param["client_secret"] = $target->credentials;
        $result = null;

        $curl->post($param, "application/x-www-form-urlencoded")
             ->then(function($req){ // start parser handler
                $h = $req->getHeader();
                $ct = $h["content_type"];

                // OIDC returns JSON
                if (strpos($ct, "application/json") !== false) {
                    return json_decode($req->getBody(), true);
                }
            }) // end parser handler
            ->then(function($res) use (&$result){ // start success handler
                if (!array_key_exists("error", $res)) {
                    $result = $res;
                }
            }); // end success handler

        return $result;
    }

    private function handleToken($response, $stateInfo=null) {
        if (empty($response) &&
            !verify_keys($response, ["access_token", "id_token"])) {
            return false;
        }

        if (!($user = $this->processAssertion($response["id_token"]))) {
            return false;
        }

        if (!($user = $this->handleUser($user))) {
            return false;
        }

        // store the tokens for revocation and other stuff
        $token = pick_keys($response, ["access_token", "refresh_token"]);

        $expires = 3600; // expires in 1h
        if (array_key_exists("expires_in", $response)) {
            $expires = $response["expires_in"];
        }

        $now = time();
        $exp = $now + $expires;

        $token["expires"] = $exp;

        if ($stateInfo && $stateInfo->refresh_id > 0) {
            $oToken = $this->manager->getTokenById($stateInfo->refresh_id);
            if (!$oToken) {
                return false; // no token found?
            }

            $token = array_merge($oToken, $token);
        }
        else {
            $target =  $this->manager->get();
            $token = array_merge($token,[
                "created"       => $now,
                "azp_id"        => $target->azp_id,
                "userid"        => $user->id
            ]);
        }

        $this->manager->storeToken($token);

        // we have two cases
        // 1. we authorized a user through the web (with redirect_uri)
        if ($stateInfo) {
            // we no longer need the state information
            $this->manager->consumeState($state);
            if ($stateInfo->redirect_uri) {
                // FIXME: we MUST track the session
                // so we can delete the token on logout
                // we also want to terminate the session if the token is revoked
                $this->startWebSession($stateInfo);
                return true;
            }
        }

        // 2. we issue a moodle token for an app
        $moodle_token = $this->grantInternalToken($user->id, $expires);

        $cliToken = pick_keys($response, ["access_token", "refresh_token", "expires_in"]);

        $cliToken = array_merge($cliToken, ["token_type" => "Bearer", "api_key" => $moodle_token]);

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
        $loader = new Loader();
        $jwt = $loader->load($jwt);

        if (!($jwt = $this->decryptJWE($jwt))) {
            return null;
        }
        if (!($jwt = $this->validateJWT($jwt))) {
            return null;
        }

        $idClaims = $this->manager->getSupportedClaims();

        $userClaims = [];
        foreach ($idClaims as $claim) {
            if ($jwt->hasClaim($claim) && $cdata = $jwt->getClaim($claim)) {
                $userClaims[$claim] = $cdata;
            }
        }

        return $userClaims;
    }

    private function decryptJWE($jwt) {
        $pk = $this->manager->getPrivateKey();
        if ($pk && $jwt instanceof JWE) {
            $jwk_set = $this->prepareKeySet($pk->crypt_key, ["use"=>"enc"]);

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

            $decrypter = Decrypter::createDecrypter([$alg], [$enc],$zip);

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

            $loader = new Loader();
            $jwt = $loader->load($payload);
            // if (!is_array($payload) || !array_key_exists('signatures', $payload)) {
        }

        if (!$jwt || !($jwt instanceof JWS)) {
            return null;
        }

        return $jwt;
    }

    private function validateJWT($jwt) {
        if (is_string($jwt)) {
            // obviously not processed yet.
            $jwt = JWSLoader::loadSerializedJsonJWS($jwt);
        }
        if (!($jwt instanceof JWS)) {
            error_log("not a JWS");
            return null;
        }

        $kid = null;
        $jku = null;

        if ($jwt->getSignature(0)->hasProtectedHeader("kid")) {
            $kid = $jwt->getSignature(0)->getProtectedHeader('kid');
        }
        if ($jwt->getSignature(0)->hasProtectedHeader("jku")) {
            $jku = $jwt->getSignature(0)->getProtectedHeader('jku');
        }
        $alg = $jwt->getSignature(0)->getProtectedHeader('alg');

        if (empty($alg)) {
            return null;
        }

        $key = $this->manager->getValidationKey($kid, $jku);
        if (!$key) {
            error_log("no key found");
            return null;
        }

        $jwk_set = $this->prepareKeySet($key->crypt_key, ["use"=>"sig"]);

        $verifier = Verifier::createVerifier([$alg]);
        try {
            $verifier->verifyWithKeySet($jwt, $jwk_set);
        }
        catch (Exception $err) {
            return null;
        }

        $azp = $this->manager->get();
        if (!$jwt->hasClaim("iss")) {
            return null;
        }
        $iss = $jwt->getClaim('iss');

        // error_log($iss . " " . $azp->issuer);

        if (empty($iss) || $iss != $azp->issuer) {
            return null;
        }

        $aud = $jwt->getClaim('aud');

        if (empty($aud) || $aud !== $azp->client_id) {
            return null;
        }
        return $jwt;
    }

    private function prepareKeySet($keyString, $keyAttr) {
        try {
            $keyObj = json_decode($keyString, true);
            $key = new JWK($keyObj);
        }
        catch (Exception $err) {
            try {
                $key = JWKFactory::createFromKey($keyString,
                                                               null,
                                                               $keyAttr);
            }
            catch (Exception $err) {
                return null;
            }
        }
        if (!$key) {
            return null;
        }

        $jwk_set = new JWKSet();
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
                core\event\user_created::create_from_userid($user->id)->trigger();

            }
        }
        $USER = $user;
        return $user;
    }

    private function handleAttributeMap($user, $claims) {
        global $CFG;

        $user = (array) $user;
        $claims = (array) $claims;

        if (!array_key_exists("id", $user)) {
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
            if (strpos($cKey, ".") !== false) {
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
}
