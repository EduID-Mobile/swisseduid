<?php

/**
 * OAuthManager is used by the auth.php for configuring the
 * plugin.
 */

require_once(__DIR__ . "/../lib.php");

use \Jose\Factory\JWKFactory;
use \Jose\Object\JWK;

class OAuthManager {
    private $azp;
    private $info;
    private $key;
    private $keyList;

    public function __construct($azp_id=null) {
        $this->azp = $azp_id;
        if (isset($azp_id)) {
            $this->info = $this->get();
            if (!isset($this->info)) {
                throw new Exception("authorization not found");
            }
        }
    }

    public function aud() {
        return $CFG->wwwroot ."/auth/swisseduid/cb.php";
    }

    public function findById($id) {
        $this->azp = $id;
        return $this->get();
    }

    public function findByUrl($url) {
        global $DB;
        if (!empty($url)) {
            if ($rec = $DB->get_record("auth_oauth_azp", ["url" => $url])) {
                $this->azp = $rec->id;
            }
        }
    }

    public function findByKid($kid) {
        global $DB;
        if (!empty($url)) {
            if ($rec = $DB->get_record("auth_oauth_keys", ["kid" => $kid])) {
				$this->azp = $rec->id;
				return $this->get();
            }
        }
    }

    public function findByIssuer($issuer) {
        global $DB;
        if (!empty($url)) {
            if ($rec = $DB->get_record("auth_oauth_keys", ["issuer" => $issuer])) {
				$this->azp = $rec->id;
				return $this->get();
            }
        }
    }

    public function isNew() {
        return !($this->azp > 0);
    }

    public function getPrivateKey() {
        global $DB;

        // $keyinfo = $DB->get_record("auth_oauth_keys", [
        //     "kid" => "private",
        //     "azp_id" => $this->azp,
        //     "jku"    => null
        // ]);
        $keyinfo = $DB->get_record("auth_oauth_keys", [
            "kid" => "private",
            "azp_id" => null,
            "jku"    => null
        ]);

        if (!$keyinfo) {
            throw new Exception("no private key found for azp");
        }
        return $keyinfo;
    }

    public function generatePrivateKey() {
        // generates a new private key
        $key = JWKFactory::createRSAKey(["size" => 4096]);
        $this->setPrivateKey(json_encode($key));
    }

    public function setPrivateKey($key) {
        global $DB;
        // error_log("key is $key");
        try {
            $ki = $this->getPrivateKey();
        }
        catch (Exception $err) {
            $ki = null;
        }

        if (!$ki) {
            $DB->insert_record("auth_oauth_keys", [
                "kid" => "private",
                "crypt_key" => $key,
                "azp_id" => $this->azp
            ]);
        }
        else {
            $DB->update_record("auth_oauth_keys", [
                "id"  => $ki->id,
                "crypt_key" => $key
            ]);
        }
        // check if we can find the private key, which throws an error
        try {
            $ki = $this->getPrivateKey();
        }
        catch (Exception $err) {
            $ki = null;
        }
        if ($ki && $ki->crypt_key !== $key) {
            throw new Exception("Error Storing Private Key");
        }
    }

    public function getAuthorities() {
        global $DB;
        return $DB->get_records("auth_oauth_azp");
    }

    public function get() {
        global $DB;
        if (isset($this->azp)) {
            return $DB->get_record("auth_oauth_azp", ["id" => $this->azp]);
        }
        return null;
    }

    public function store($info) {
        global $DB;

        foreach (["name", "url", "client_id"] as $attr) {
            if (!array_key_exists($attr, $info)) {
                throw new Exception("attribute missing");
            }
        }
        foreach ( array_keys($info) as $attr) {
            if (!in_array($attr, ["name", "url", "client_id", "flow", "auth_type", "credentials", "issuer", "authorization_endpoint", "token_endpoint", "revocation_endpoint", "end_session_endpoint", "registration_endpoint", "introspection_endpoint", "jwks_uri"])) {
                unset($info[$attr]);
            }
        }

        if (isset($this->azp) && $this->azp > 0) {
            $info["id"] = $this->azp;
            $DB->update_record("auth_oauth_azp", $info);
        }
        else {
            // add default attribute mapping on creation.
            if (!array_key_exists("attr_map", $info) || empty($info["attr_map"])) {
                $info["attr_map"] = $this->getDefaultMapping();
            }

            $id = $DB->insert_record("auth_oauth_azp", $info);
            $this->azp = $id;
        }
    }

    public function remove() {
        global $DB;
        if ($this->azp > 0) {
            $DB->delete_records("auth_oauth_azp", ["id" => $this->azp]);

            // verify that the authority is gone
            $i = $DB->get_record("auth_oauth_azp", ["id" => $this->azp]);
            if ($i && $i->id == $this->azp) {
                throw new Exception("Azp Not Removed");
            }
        }
    }

    public function deactivate() {
        global $DB;
        if ($this->azp > 0) {
            $DB->update_record("auth_oauth_azp", ["id" => $this->azp, "inactive" => 1]);
        }
    }

    public function activate() {
        global $DB;
        if ($this->azp > 0) {
            $DB->update_record("auth_oauth_azp", ["id" => $this->azp, "inactive" => 0]);
        }
    }

    public function getKeys() {
        global $DB;
        return $DB->get_records("auth_oauth_keys", ["azp_id" => $this->azp]);
    }


    public function getKey($keyid) {
        global $DB;
        return $DB->get_record("auth_oauth_keys", ["azp_id" => $this->azp, "id" => $keyid]);
    }

    public function getValidationKey($kid, $jku) {
        global $DB;
        if (empty($kid) && empty($jku)) {
            return null;
        }
        $attr = ["kid" => $kid, "jku" => $jku];
        if (empty($kid)) {
            $attr["kid"] = null;
        }
        if (empty($jku)) {
             unset($attr["jku"]);
        }
        $keyset = $DB->get_record("auth_oauth_keys", $attr);
        if ($keyset) {
            $this->azp = $keyset->azp_id;
        }
        else {
             error_log("key not found for " . json_encode($attr));
        }
        return $keyset;
    }

    public function updateKeySet($url) {
        $self = $this;
        $curl = new Curler\Request($url);
        $curl->get()
             ->then(function($req) {
                return json_decode($req->getBody(), true);
             })
             ->then(function($keys) use ($self) {
                if (!empty($keys) &&
                    array_key_exists('keys', $keys) &&
                    !empty($keys["keys"])) {

                    foreach ($keys["keys"] as $k) {
                        $attr = [];
                        $attr["kid"] = $k["kid"];
                        $attr["jku"] = $url;
                        $attr["crypt_key"] = json_encode($k);

                        $self->storeKey($attr);
                    }
                }
            });  // end success handler
    }

    public function storeAuthority($config) {
        global $CFG;
        $url = $config["url"];
        $azpData = pick_keys($config, ["name", "url", "flow", "credentials", "issuer", "client_id"]);

        if (!array_key_exists("client_id", $azpData)) {
            $azpData["client_id"] = $CFG->wwwroot;
        }
        if (!array_key_exists("credentials", $azpData)) {
            $azpData["credentials"] =random_string(160);
        }
        // load the configuration
        $self = $this;

        $curl = new Curler\Request(trim($azpData["url"], "/"));
        $curl->setPathInfo("/.well-known/openid-configuration"); // <- try OIDC discovery
        $curl->get()
             ->then(function ($req) {
                return json_decode($req->getBody(), true);
             })
             ->then(function ($data) use ($self, $azpData) {
                 if (!empty($data)) {
                    $attrs = [
                        "authorization_endpoint",
                        "token_endpoint",
                        "revocation_endpoint",
                        "end_session_endpoint",
                        "registration_endpoint",
                        "introspection_endpoint",
                        "jwks_uri",
                        "issuer"
                    ];
                    $azpData = array_merge($azpData, pick_keys($data, $attrs));
                    $self->store($azpData);

                    if (has_key($azpData, "jwks_uri")) {
                        $self->updateKeySet($azpData["jwks_uri"]);
                    }
                 }
             }); // end success handler
    }

    public function storeMapping($arrMap) {
        $arrMap = pick_keys(obj2array($arrMap), $this->getUserAttributes());

        if (!empty($arrMap)) {
            $this->store(["attr_map" => json_encode($arrMap)]);
        }
    }

    public function storeKey($keyInfo) {
        global $DB;
        $keyInfo = (array) $keyInfo;
		$stored_key_entry = false;

        verify_keys($keyInfo, ["kid", "crypt_key"], "Missing Key Attribute");

		if (array_key_exists("keyid", $keyInfo) && !empty($keyInfo["keyid"])) {
			$keyInfo["id"] = $keyInfo["keyid"];
			// extract the key by using the id
			$stored_key_entry = $DB->get_record("auth_oauth_keys", array('id' => $keyInfo["id"]));
		} else if (array_key_exists("kid", $keyInfo) && !empty($keyInfo["kid"])) {
			$stored_key_entry = $DB->get_record("auth_oauth_keys", array('kid' => $keyInfo["kid"]));
			if($stored_key_entry != false) {
				$keyInfo["id"] = $stored_key_entry->id;
			}
		}

        if (array_key_exists("key", $keyInfo) && !empty($keyInfo["key"])) {
            $keyInfo["crypt_key"] = $keyInfo["key"];
        }

        $keyInfo = pick_keys($keyInfo, ["crypt_key", "id", "kid", "jku", "token_id"]);
        $keyInfo["azp_id"] = $this->azp;

        /* if (array_key_exists("id", $keyInfo )) { */
        if ($stored_key_entry === false) {
            $DB->insert_record("auth_oauth_keys", $keyInfo);
        } else {
            $DB->update_record("auth_oauth_keys", $keyInfo);
        }
    }

    public function removeKey($id) {
        global $DB;

        $DB->delete_records("auth_oauth_keys", ["id" => $id, "azp_id" => $this->azp_id]);
    }

    public function createState($info) {
        global $DB;

        if (!$this->azp) {
            return null;
        }

        // create random string using moodle's random string function
        $attr = [
            "state" => random_string(15),
            "azp_id" => $this->azp
        ];

        if (!empty($info)) {
            foreach (["refresh_id", "redirect_uri"] as $k) {
                if (array_key_exists($k, $info)) {
                    $attr[$k] = $info[$k];
                }
            }
        }

        $DB->insert_record("auth_oauth_state", $attr);

        return $attr["state"];
    }

    public function getState($state) {
        global $DB;

        $rec = $DB->get_record("auth_oauth_state", ["state" => $state]);
        $this->azp = $rec->azp_id;
        return $rec;
    }

    public function consumeState($state) {
        global $DB;
        $DB->delete_records("auth_oauth_state", ["state" => $state]);
    }

    public function getMapping() {
        if ($rec = $this->get() && !empty($rec->attr_map)) {
            try {
                $mapping = json_decode($rec->attr_map, true);
            }
            catch (Exception $err) {
                return $this->getDefaultMapping();
            }
            return $mapping;
        }
        return $this->getDefaultMapping();
    }

    public function storeToken($token) {
        global $DB;
        $funcname = "update_record";
        if (!array_key_exists("id", $token)) {
            $token["initial_access_token"]  = $token["access_token"];
            $token["initial_refresh_token"] = $token["refresh_token"];
            $funcname = "insert_record";
        }

        $DB->$funcname("auth_oauth_tokens", $token);
    }

    public function getTokenById($tokenId) {
        global $DB;
        $retval = $DB->get_record("auth_oauth_tokens", ["id"=>$tokenId]);
        if ($retval) {
            return (array) $retval;
        }
        return null;
    }

    public function getToken($token, $type) {
        global $DB;
        $attr = [
            $type => $token
        ];
        $retval = $DB->get_record("auth_oauth_tokens", $attr);
        if ($retval) {
            return (array) $retval;
        }
        return null;
    }

    public function getPublicJWK() {
        $pk = $this->getPrivateKey();
        $ko = json_decode($pk->crypt_key, true);
        $ko["use"] = "enc";
        $key = new JWK($ko);
        // $key = JWKFactory::createFromKey($pk->crypt_key,
        //                                  null,
        //                                  ["use"=>"enc"]);
        return json_encode($key->toPublic());
    }

    public function getUserAttributes() {
        return array_keys($this->getDefaultMapping());
    }

    public function getSupportedClaims() {
        return $this->getStandardClaims();
    }

    public function getStandardClaims() {
        // this should get loaded from the database for
        // an azp
        return [
            "sub",
            "email", "given_name", "family_name", "middle_name", "nickname",
            "preferred_username", "profile", "picture", "website",
            "email_verified", "gender", "birthdate", "zoneinfo", "locale",
            "phone_number", "phone_number_verified", "updated_at",
            "address.street_address", "address.city", "address.locality",
            "address.country", "address.postal_code", "address.region"
        ];
    }

    public function getDefaultMapping() {
        return [
            "email" => "email",
            "firstname" => "given_name",
            "lastname" => "family_name",
            "idnumber" => "",
            "icq" => "",
            "skype" => "",
            "yahoo" => "",
            "aim" => "",
            "msn" => "",
            "phone1" => "phone_number",
            "phone2" => "",
            "institution" => "",
            "departement" => "",
            "address" => "address.street_address",
            "city" => "address.locality",
            "country" => "address.country",
            "lang" => "locale",
            "url" => "website",
            "middlename" => "middle_name",
            "firstnamephonetic" => "",
            "lastnamephonetic" => "",
            "alternatename" => "nickname"
        ];
    }
}
