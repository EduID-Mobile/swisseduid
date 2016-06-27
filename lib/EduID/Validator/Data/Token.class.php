<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */

namespace EduID\Validator\Data;

use EduID\Validator\Base as Validator;
use EduID\Model\Token as TokenModel;
use EduID\Model\User as UserModel;

class Token extends Validator {
    private $user;
    private $jtoken;

    protected function validate() {
        if (!$this->checkDataForMethod()) {
            return false;
        }

        if (!$this->checkAuthToken()) {
            return false;
        }

        if (!$this->checkDataFields(array("grant_type"))) {
            return false;
        }

        $aFields = array();
        if (array_key_exists("grant_type", $this->data)) {
            switch ($this->data["grant_type"]) {
                case "authorization_code": // Section 4.1.3
                    $aFields = array("redirect_uri", "code", "client_id");
                    break;
                case "password": // Section 4.3.2
                    $aFields = array("username", "password");
                    break;
                case "jwt_assertion":
                    // scope is optional
                    $aFields = array("assertion");
                    break;
                case "client_credentials": // Section 4.4
                default:
                    break;
            }
        }
        else if (array_key_exists("request_type", $this->data)) {
            if ($this->data["request_data"] == "code") {
                $aFields = array("client_id", "redirect_uri");
            }
        }

        if (!$this->checkDataFields($aFields)) {
            // problem already logged
            return false;
        }

        return true;
    }

//    private function checkGrantType() {
//        if (method_exists($this, "validate" . $this->data["grant_type"])) {
//            return call_user_func(array($this, "_" . $this->data["grant_type"]));
//        }
//
//        $this->log("bad grant type found " . $this->data["grant_type"]);
//        return false;
//    }

    protected function validate_post_authorization_code() {
        $token = $this->service->getAuthToken();

        if ($token["access_key"] != $this->data["code"]) {
            $this->log("mismatching code presented");
            return false;
        }

        if (!(array_key_exists("extra", $token) &&
              array_key_exists("client_type", $token["extra"]) &&
              $token["extra"]["client_type"] == $this->data["client_id"])) {

            $this->log("mismatching eduid app id presented");
            return false;
        }

        $service = $this->service->getTargetService();
        $service->findServiceByURI($this->data["redirect_uri"]);

        if (!$service->hasUUID()) {
            $this->log("no service found for URI " . $this->data["redirect_uri"]);
            return false;
        }

        return true;
    }

    protected function validate_post_client_credentials() {
        // verify claims
        $jwt = $this->service->getJWT();

        if (!$jwt->hasClaim("sub") ||
            empty($jwt->getClaim("sub")) ||
            !$jwt->hasClaim("name") ||
            empty($jwt->getClaim("name"))) {

            $this->log("missing instance information for client credentials " . json_encode($jwt->getClaims()));
            return false;
        }
        return true;
    }

    protected function validate_post_password() {
        // ckeck if we know the requested user
        $this->user = new UserModel($this->db);
        $this->user->setDebugMode($this->getDebugMode());

        if (!$this->user->findByMailAddress($this->data["username"])) {
            $this->log("user not found");
            $this->service->authentication_required();
            return false;
        }
        return true;
    }

    protected function validate_post_code() {
        $token = $this->service->getAuthToken();
        if ($token["client_id"] != $this->data["client_id"]) {
            $this->log("client id mismatch");
            return false;
        }

        // verify that we know the redirect URI
        $service = $this->service->getTargetService();
        if (!$service->findServiceByURI($this->data["redirect_uri"])) {
            $this->log("target uri not found");
            return false;
        }
        
        return true;
    }

    protected function validate_post_validate() {
        // the JTI must be issued to the same service UUID as the authToken

        if (!array_key_exists("jti", $this->data)) {
            $this->log("missing jti");
            $this->service->not_found();
            return false;
        }

        $kid = trim($this->data["jti"]);
        if (empty($kid)) {
            $this->log("missing jti");
            $this->service->not_found();
            return false;
        }

        $token = $this->service->getAuthToken();
        $jtm = new TokenModel($this->db);
        $jtm->setDebugMode($this->getDebugMode());

        if (!$jtm->findToken($kid)) {
            $this->log("jto not found");
            $this->service->not_found();
            return false;
        }

        $jtoken = $jtm->getToken();
        if ($jtoken["service_uuid"] != $token["service_uuid"]) {
            $this->log("token is not issued to the service");
            $this->service->not_found();
            return false;
        }

        if (empty($jtoken["user_uuid"])) {
            $this->log("token was not issued to a user");
            $this->service->not_found();
            return false;
        }

        // not find a user
        $um = new UserModel($this->db);
        $um->setDebugMode($this->getDebugMode());
        if (!$um->findByUUID($jtoken["user_uuid"])) {
            $this->log("user not found");
            $this->service->not_found();
            return false;
        }

        $um->getAllProfiles();
        $jtoken["email"] = $profiles[0]["mailaddress"];

        $this->jtoken = $jtoken;

        return true;
    }

    public function getUser() {
        return $this->user;
    }

    public function getToken() {
        return $this->jtoken;
    }
}
?>
