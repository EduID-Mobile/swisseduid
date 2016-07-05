<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */

namespace EduID\Model;

use Lcobucci\JWT;
use Lcobucci\JWT\Parser as TokenParser;

class Authority extends ModelFoundation {
    private $service;

    public function findServiceByUrl($serviceUrl) {
        global $DB;
        if (!$this->service = $DB->get_record('auth_eduid_authorities',
                                              ['authority_url'=>$serviceUrl]))
        {
            return false;
        }


        return true;
    }

    public function authorityId() {
        return $this->service ? $this->service->authority_id : 0;
    }

    public function verifyAssertion($serviceToken) {
        // returns claims;
        $this->mark(" 1");
        if (empty($serviceToken)) {
            return null;
        }

        $this->mark(" 2");
        $jwt = new TokenParser();
        try {
            $token = $jwt->parse($serviceToken);
        }
        catch (InvalidArgumentException $e) {
            $this->log("invalid arguent: " . $e->getMessage());
            return null;
        }
        catch (RuntimeException $e) {
            $this->log("runtime exception: " . $e->getMessage());
            return null;
        }

        $this->mark(" 3");

        if (!isset($token)) {
            $this->log("no token");
            return null;
        }

        $iss = $token->getClaim("iss");

        if (empty($iss)) {
            $this->log("no issuer?");
            return null;
        }

        $this->log($iss);
        if (!$this->findServiceByUrl($iss)) {
            $this->log("no related authority found");
            return null;
        }

        $sharedToken = json_decode($this->service->authority_shared_token);

        $sign_key = $sharedToken->mac_key;

        if (empty($sign_key)) {
            $this->log("no sign key present");
            return null;
        }

        $alg = $token->getHeader("alg");
        if (empty($alg)) {
            $this->log("no alg header found");
            return null;
        }

        if(!$signer = $this->getSigner($alg)) {
            $this->log("cannot find signer");
            return null;
        }

        $this->log(get_class($signer) . " $sign_key");
        if (!$token->verify($signer, $sign_key)) {
            $this->log("cannot verify token signature");
            return null;
        }
        $this->mark(" 4");
        $this->log(json_encode($token->getClaims()));
        return $token->getClaims();
    }

    public function getSigner($alg="") {
        $signer = null;

        if (empty($alg)) {
            if ($this->token) {
                $alg = $this->token["mac_algorithm"];
            }
            else {
                return null;
            }
        }

        list($algo, $level) = explode("S", $alg);

        switch ($algo) {
            case "H": $algo = "Hmac"; break;
            case "R": $algo = "Rsa"; break;
            case "E": $algo = "Ecdsa"; break;
            default: $algo = ""; break;
        }
        switch ($level) {
            case "256":
            case "384":
            case "512":
                break;
            default: $level = ""; break;
        }

        if (!empty($algo) && !empty($level)) {
            // NOTE: for dynamic namespaced classes the fully qualified name is needed.
            $signerClass = "Lcobucci\\JWT\\Signer\\" . $algo . "\\Sha" . $level ;
            $signer = new $signerClass();
        }

        return $signer;
    }
}

?>
