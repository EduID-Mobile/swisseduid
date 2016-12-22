<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */
namespace EduID\Model;

class Token extends ModelFoundation {
    protected $root_token;
    protected $token;
    protected $validFields = ['id',
                              'userid',
                              'authority_id',
                              'parent_id',
                              'access_token',
                              'expiration',
                              'expires_in',
                              'scope',
                              'refresh_token',
                              'client',
                              'algorithm',
                              'sign_key',
                              'decrypt_key',
                              'consumed',
                              'token_type'
                             ];

    protected $uuid;        // respective uuid

    protected $algorithm      = "HS256"; // use only JWA names
    protected $expires_in     = 0; // default is unlimited

    protected $tokenLength;

    public function __construct($options=array()) {
        // this should be set via the options
        $this->tokenLength = array(
            "access_token" => 40,
            "refresh_token" => 40,
            "sign_key"      => 100
        );

        $this->setOptions($options);
    }

    public function setOptions($options) {
        if (!empty($options)) {
            if (array_key_exists("expires_in", $options)) {
                $this->expires_in = $options["expires_in"];
            }

            // we allow only JWA algorithms
            $aAlg = explode(" ",
                            "HS256 HS384 HS512 RS256 RS384 RS512 ES256 ES384 ES512 hmac-sha-256");

            if (array_key_exists("algorithm", $options) &&
                in_array($options["algorithm"], $aAlg)) {

                // if we get an old MAC name, transpose to the new JWA name
                if ($options["algorithm"] == "hmac-sha-256") {

                    $options["algorithm"] = "HS256";
                }
                $this->algorithm = $options["algorithm"];
            }
            if (array_key_exists("alg", $options) &&
                in_array($options["alg"], $aAlg)) {

                if ($options["alg"] == "hmac-sha-256") {

                    $options["alg"] = "HS256";
                }
                $this->algorithm = $options["alg"];
            }
            if (array_key_exists("expires_in", $options)) {
                $this->expires_in = $options["expires_in"];
            }
        }
    }

    /**
     * @public @function setToken()
     *
     * initialises using a token (provided by the token validator)
     */
    public function setToken($token) {
        $bOK = true;
        foreach (get_object_vars($token) as $tk) {
            if (!in_array($tk, $this->validFields)) {
                $bOK = false;
                break;
            }
        }

        if ($bOK){

            $this->token = $token;
            $this->findRootToken();
        }
    }

    /**
     * @function setRootToken()
     *
     * used by the OAuth Service to pass data from the TokenValidator
     */
    public function setRootToken($token) {
        if (!empty($token)) {

            $this->root_token = $token;
         }
    }

    public function getToken() {
        return $this->token;
    }

    public function getRootToken() {
        return $this->root_token;
    }

    public function getTokenResponse() {
        $this->token->kid = $this->token->id;

        return $this->reduceFields($this->token, ["access_token",
                                                  "refresh_token",
                                                  "expires_in",
                                                  "scope",
                                                  "kid",
                                                  "sign_key",
                                                  "algorithm",
                                                  "token_type"
                                                 ]);
    }

    // create a new TM instance with the active token as root
    public function getIssuer() {
        $tm = null;
        if ($this->token) {
            $tm = new Token();
            $tm->setDebugMode($this->getDebugMode());
            $tm->setRootToken($this->token);
        }
        return $tm;
    }

    public function cloneIssuer() {
        $tm = null;

        if ($this->root_token) {
            $tm = new Token();
            $tm->setDebugMode($this->getDebugMode());
            $tm->setRootToken($this->root_token);
        }

        return $tm;
    }

    public function getUser() {
        global $DB;

        if (!empty($this->token) &&
            !empty($this->token->userid)) {
            return $DB->get_record('user', ['userid'=>$this->token->userid]);
        }

        return null;
    }

    public function checkTokenValue($key, $value) {
        if ($this->token &&
            property_exists($this->token, $key) &&
            !empty($this->token->$key) &&
            $this->token->$key === $value) {

            return true;
        }
        return false;
    }

    public function hasTokenValue($key) {
        if ($this->token &&
            property_exists($this->token, $key) &&
            (!empty($this->token->$key) || $this->token->$key > 0)) {
            return true;
        }
        return false;
    }

    public function checkTokenValues($valueList) {
        if ($this->token) {
            if(!empty($valueList) &&
               is_array($valueList)) {

                return $this->checkMandatoryFields($this->token, $valueList);
            }
            return true;
        }
        return false;
    }

    /**
     * create a basic token in memory instance
     *
     * @public function arrary newToken($type)
     *
     * @param string $type - token type
     *
     * initializes a basic token. If type is Bearer, then an identical
     * access_key and kid are generated. For all other types a full token
     * is generated, consisting of a unique access_key, kid, mac_key, and
     * mac_algorithm.
     *
     * If token expiration is set, then this function also includes the
     * expiration timestamp;
     */
    public function newToken() {
        $newToken = new \stdClass();

        $newToken->consumed = 0;

        $newToken->access_token  = $this->randomString($this->tokenLength["access_token"]);
        $newToken->refresh_token = $this->randomString($this->tokenLength["refresh_token"]);
        $newToken->sign_key      = $this->randomString($this->tokenLength["sign_key"]);

        if ($this->root_token) {
            $newToken->parent_id = $this->root_token->id;
        }

        if (!empty($this->algorithm)) {
            $newToken->algorithm = $this->algorithm;
        }
        else {
            $newToken->algorithm = "HS256"; // meaningful baseline
        }

        if ($this->expires_in > 0) {
            $newToken->expiration = time() + $this->expires_in;
        }

        return $newToken;
    }

    /**
     * function addToken
     *
     * initialise a token and stores it to the database
     *
     * this function sets external information such as the user_uuid etc.
     *
     * Allowed token values are
     * * userid
     * * client
     * * authority_id
     * * scope
     * * algorithm as a client can set a preference
     *
     * At least one of user_uuid, service_uuid, or client_id MUST be set.
     */
    public function addToken($token=array()) {
        global $DB;

        $newToken = $this->newToken();

        if (isset($this->root_token)) {
            $newToken->parent_id = $this->root_token->id;
        }

        if (
            (array_key_exists("userid", $token) && !empty($token["userid"])) ||
            (array_key_exists("authority_id", $token) && !empty($token["authority_id"])) ||
            (array_key_exists("client", $token) && !empty($token["client"]))
        ) {

            foreach(array("userid",
                          "client",
                          "authority_id",
                          "scope",
                          "algorithm",
                          "sign_key",
                          "decrypt_key",
                          "token_type",
                          "scope") as $key) {

                // inherit different approaches from the root token
                if (!empty($this->root_token) &&
                    property_exists($this->root_token,$key) &&
                    !empty($this->root_token->$key)) {

                    $newToken->$key = $this->root_token->$key;
                }

                if (array_key_exists($key, $token) &&
                    !empty($token[$key])) {

                    $newToken->$key = $token[$key];
                }
            }

			// delete previous tokens, keep only one for the same user; this is a temporary measure
            $DB->delete_records('auth_eduid_tokens', array('userid' => $token['userid']));

            $newToken->id = $DB->insert_record('auth_eduid_tokens', $newToken);

            if ($newToken->id) {
                $this->token = $newToken;
            }

            // add the token information into the external_token relation
            if ($newToken->token_type == "urn:eduid:token:app") {
                $this->addMoodleToken();
            }
        }
    }

    private function addMoodleToken() {
        // stolen from webservice/lib.php 308
        global $DB;

        $norestrictedservices = $DB->get_records('external_services',
                                                 array('restrictedusers' => 0));
        $serviceidlist = array();
        foreach ($norestrictedservices as $service) {
            $serviceidlist[] = $service->id;
        }

        // create a token for the service which have no token already
        // MOODLE refuses to have the same token several times for the user.
        // FIXME: the correct behaviour would be to request a token for a special service type.
        // The reality is that Moodle's token handling would no allow this
        foreach ($serviceidlist as $serviceid) {
            // create the token for this service
            $newtoken = new \stdClass();
            $newtoken->token              = $this->token->access_token;
            // check that the user has capability on this service
            $newtoken->tokentype          = EXTERNAL_TOKEN_PERMANENT;
            $newtoken->userid             = $this->token->userid;
            $newtoken->externalserviceid  = $serviceid;
            // TODO MDL-31190 find a way to get the context - UPDATE FOLLOWING LINE
            $newtoken->contextid          = \context_system::instance()->id;

            $newtoken->creatorid          = $this->token->userid;
            $newtoken->timecreated        = time();
            $newtoken->lastaccess         = time();

            $newtoken->validuntil         = $this->token->expiration ? $this->token->expiration : 0;

            $DB->insert_record('external_tokens', $newtoken);
            break; // ensure that the same token is created only once. (hopefully for the correct service set)
        }
    }

    public function invalidateToken() {
        $this->consumeToken();
    }

    public function consumeRootToken() {
        if (!empty($this->token)) {
            $this->consume_token_db($this->token);
        }
    }

    public function consumeToken() {
        if (!empty($this->token)) {
            $this->consume_token_db($this->token);
        }
    }

    public function findTokenByKid($kid) {
        global $DB;

        $this->token = $DB->get_record('auth_eduid_tokens',
                                       ['id'=>$kid]);
        return ($this->token != null);
    }

    public function findToken($access_token, $active=true) {
        global $DB;

        $this->token = $DB->get_record('auth_eduid_tokens',
                                       ['access_token'=>$access_token]);

        if (!$this->token) {
             $this->token = $DB->get_record('auth_eduid_tokens',
                                            ['refresh_token'=>$access_token]);
        }

        if ($active && $this->token->consumed == 1) {
            $this->token = null;
        }

        return ($this->token != null);
    }

    public function findRootToken() {
        global $DB;

        if (!empty($this->token) &&
            property_exists($this->token, "parent_id") &&
            !empty($this->token->parent_id)) {

            $this->root_token = $DB->get_record('auth_eduid_tokens',
                                                ['id'=>$this->token->parent_id]);
        }
    }

    public function eraseToken() {
        global $DB;

        if (!empty($this->token)) {
            $DB->delete_records('auth_eduid_tokens', array('id' => $this->token->id));
        }
    }

    private function consume_token_db($token) {
        global $DB;

        $token->consumed = 1;
        $DB->set_field('auth_eduid_tokens', 'consumed', 1, ['id' => $this->token->id]);
        $DB->set_field('auth_eduid_tokens', 'consumed', 1, ['parent_id' => $this->token->id]);

//        if ($subTokens = $DB->get_recordset('auth_eduid_tokens',
//                                            ['parent_id' => $token->id])) {
//
//            foreach ($subTokens as $t) {
//                $this->consume_token_db($t);
//            }
//        }
    }

    public function getSigner($alg="") {
        $signer = null;

        if (empty($alg)) {
            if ($this->token) {
                $alg = $this->token->algorithm;
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
