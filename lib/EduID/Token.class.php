<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */

namespace EduID;

use EduID\ServiceFoundation;
use EduID\Validator\Data\Token as TokenDataValidator;
use EduID\Model\User;
use EduID\Model\Service;

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

        $this->tokenValidator->resetAcceptedTokens(array("Bearer", "MAC"));

        $this->dataValidator = new TokenDataValidator($this->db);

        $this->addDataValidator($this->dataValidator);
    }

    public function verifyRawToken($code) {
        return $this->tokenValidator->verifyRawToken($code);
    }

    public function verifyTokenClaim($claim, $value) {
        return $this->tokenValidator->verifyJWTClaim($claim, $value);
    }

    public function getAuthToken() {
        return $this->tokenValidator->getToken();
    }

    public function getJWT() {
        return $this->tokenValidator->getJWT();
    }

    public function getTargetService() {
        if (!isset($this->serviceManager)) {
            $this->serviceManager = new ServiceManager($this->db);
        }
        return $this->serviceManager;
    }

    protected function prepareOperation() {
        // this is a helper to set the assertion grant to something we can handle.
        // this small hack is needed make post_jwt_assertion() calls possible.
        $assertion_type = 'urn:ietf:param:oauth:grant-type:jwt-bearer';
        $assertion_name = 'jwt_assertion';

        if ($this->inputData &&
            array_key_exists("grant_type", $this->inputData) &&
            $this->inputData["grant_type"] == $assertion_type) {

            $this->inputData["grant_type"] = $assertion_name;
        }

        parent::prepareOperation();
    }

    protected function post_password() { // OAuth2 Section 4.3.2
        $token = $this->getAuthToken();
        $tokenType = "MAC";
        $tm = $this->tokenValidator->getTokenIssuer($tokenType);

        $um = $this->dataValidator->getUser();
        if ($um->authenticate($this->inputData["password"])) {

            $tm->addToken(array("user_uuid" => $um->getUUID()));

            $ut = $tm->getToken();

            $this->data = array(
                "access_token"  => $ut["access_key"],
                "token_type"    => strtolower($ut["token_type"]),
                "kid"           => $ut["kid"],
                "mac_key"       => $ut["mac_key"],
                "mac_algorithm" => $ut["mac_algorithm"]
            );

            if (array_key_exists("expires_in", $ut)) {
                $this->data["expires_in"] = $ut["expires_in"];
            }
        }
        else {
            $this->log("failed to authenticate user " . $this->inputData["username"]);
            $this->forbidden();
        }
    }

    protected function post_client_credentials() {
        $token = $this->getAuthToken();

        // transpose token claims
        $jwt = $this->getJWT();
        $this->inputData["device_name"] = $jwt->getClaim("name");
        $this->inputData["device_id"]   = $jwt->getClaim("sub");

        $tokenType = "MAC";
        $tm = $this->tokenValidator->getTokenIssuer($tokenType);

        // get the root token info
        $clientToken = $this->tokenValidator->getToken();

        $token_extra = array("client_type" => $clientToken["client_id"],
                             "device_name" => $this->inputData["device_name"]);

        // get extra info from the current token
        $tm->addToken(array("client_id" => $this->inputData["device_id"],
                            "extra"     => $token_extra));

        $token = $tm->getToken();

        $this->data = array(
            "access_token"  => $token["access_key"],
            "token_type"    => strtolower($token["token_type"]),
            "kid"           => $token["kid"],
            "mac_key"       => $token["mac_key"],
            "mac_algorithm" => $token["mac_algorithm"]
        );

        if (array_key_exists("expires_in", $token) && !empty($token["expires_in"])) {
            $this->data["expires_in"] = $token["expires_in"];
        }
    }


    protected function post_jwt_assertion() {
        // RFC 7121 defines this as 'urn:ietf:param:oauth:grant-type:jwt-bearer'

    }

    protected function post_authorization_code() { // RFC6749 Section 4.1
        // service needs to be validated by tokendata validator

        // we don't allow code authorisation here (well, we are the authority)
        $this->forbidden(json_encode(["error"=>"invalid_scope"]));
    }

    protected function post_validate() {
        $t = $this->dataValidator->getToken();

        $this->data["sub"]    = $t["client_id"];
        $this->data["azp"]    = $t["extra"]["client_type"];
        $this->data["iat"]    = $t["issued_at"];
        $this->data["email"]  = $t["email"];

    }
}

?>
