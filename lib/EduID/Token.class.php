<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */

namespace EduID;

use EduID\ServiceFoundation;
use EduID\Validator\Data\Token as TokenDataValidator;
use EduID\Model\User as UserModel;
use EduID\Model\Authority as AuthorityModel;
use EduID\Model\Token as TokenModel;

//
//require_once("Models/class.EduIDValidator.php");
//require_once("Models/class.UserManager.php");
//require_once("Models/class.ServiceManager.php");
//require_once("Models/class.TokenDataValidator.php");

use Lcobucci\JWT as JWT;

/**
 *
 */
class Token extends ServiceFoundation {
    private $userValidator;
    private $serviceManager;

    public function __construct() {
        parent::__construct();

        $this->setOperationParameter("grant_type");

        $this->tokenValidator->resetAcceptedTokens(["Bearer"]);
        $this->tokenValidator->ignoreOperations(["post_jwt_assertion"]);

    //    $this->dataValidator = new TokenDataValidator();

      //  $this->addDataValidator($this->dataValidator);
    }

    protected function prepareOperation() {
        $this->mark();
        // this is a helper to set the assertion grant to something we can handle.
        // this small hack is needed make post_jwt_assertion() calls possible.
        $assertion_type = 'urn:ietf:param:oauth:grant-type:jwt-bearer';

        if ($this->inputData &&
            array_key_exists("grant_type", $this->inputData) &&
            $this->inputData["grant_type"] == $assertion_type) {

            $this->inputData["grant_type"] = 'jwt_assertion';
        }

        parent::prepareOperation();
    }

    protected function post_password() { // OAuth2 Section 4.3.2
        // MOODLE handles the password authorization separately.
        return $this->forbidden();
    }

    protected function post_client_credentials() { // OAuth2 Section 4.4
        return $this->forbidden();
    }

    protected function post_jwt_assertion() {
        $this->mark();

        // RFC 7121 defines this as 'urn:ietf:param:oauth:grant-type:jwt-bearer'

        // used by the EduID App to obtain its service grant token
        $token = $this->inputData["assertion"];

        // use system configuration!
        $authority = new AuthorityModel();

        if ($claims = $authority->verifyAssertion($token)) {
            $this->mark(json_encode($claims));
            $aud = $claims["aud"];
            // the service MUST be the audience
            $myurl = $this->targetAudience();

            if ($aud == $myurl) {
                // get claims
                $um = new UserModel();
                $u = new \stdClass();

                $u->username  = $claims["sub"]->getValue();
                $u->email     = $claims["email"]->getValue();
                $u->firstname = $claims["given_name"]->getValue();
                $u->lastname  = $claims["family_name"]->getValue();

                $um->updateUserInfo($u);

                if ($um->hasUser()) {

                    // use system configuration!
                    $tm = new TokenModel();

                    $client = $claims["azp"]->getValue();

                    $this->mark();

                    $tm->addToken(["token_type"   => "urn:eduid:token:client",
                                   "client"       => $client,
                                   "authority_id" => $authority->authorityId(),
                                   "userid"       => $um->userId()]);

                    $this->log( "assertion is ok" );
                    $this->data = $tm->getTokenResponse();
                }
                else {
                    $this->data = "NO USER SET";
                }
            }
            else {
                $this->forbidden();
            }
        }
    }

    protected function post_refresh() {
        // RFC 6749 Section
        // used by apps to extend their app token
        $ftm   = new TokenModel(["expires_in" => 86000]);
        $ftm->findtoken($this->inputData["refresh_token"], false);

        $atoken = $this->tokenValidator->getToken();

        $token = $ftm->getToken();

        if ($token &&
            $atoken &&
            $token->id == $token->id) {

            $ftm->findRootToken();

            $tm = $ftm->cloneIssuer();

            // create a new token using the same information as
            // the old token
            $tm->addToken([
                "token_type" => "urn:eduid:token:app",
                "client"     => $token->client
            ]);

            $this->data = $tm->getTokenResponse();

            // invalidate the previous token
            $ftm->consumeToken();
        }
    }

    protected function post_authorization_code() { // RFC6749 Section 4.1
        // used by the eudid app to obtain app tokens

        $token = $this->tokenValidator->getToken();

        $client = $this->inputData["client_id"];

        // the code is a JWT with the client as subject and that is signed
        // using the same key as the authorization token.

        $jwt = $this->tokenValidator->processJWT($this->inputData["code"]);
        if ($jwt &&
            $jwt->getClaim("aud") == $this->targetAudience() &&
            $jwt->getClaim("sub") == $client &&
            $jwt->getClaim("iss") == $token->client)  {

            $tm = $this->tokenValidator->getTokenIssuer();

            $tm->setOptions(["expires_in" => 86000]);

            $tm->addToken(["token_type" => "urn:eduid:token:app",
                           "client"     => $client]);

            $data = $tm->getTokenResponse();

            $this->data = [];
            $this->data["access_token"]  = $data->access_token;
            $this->data["refresh_token"] = $data->refresh_token;
            if (property_exists($data, "scope")) {
                $this->data["scope"] = $data->scope;
            }
            if (property_exists($data, "expires_in")) {
                $this->data["expires_in"] = $data->expires_in;
            }
        }
        else {
            $this->forbidden(json_encode(["error"=>"invalid_scope"]));
        }
    }

    private function findIssuer($issuer) {
        global $DB;
        return $DB->get_record('auth_eduid_authorities',
                               ['authority_url'=>$issuer]);
    }

    private function targetAudience() {
        $myurl = ($_SERVER["HTTPS"] ? "https" : "http") . "://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];

        return $myurl;
    }
}

?>
