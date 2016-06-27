<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */
namespace EduID\Model;

class Token extends DBManager{
    protected $root_token;
    protected $token;
    protected $token_list;
    protected $active_token;

    protected $root_token_type;   // service, client, user
    protected $token_type = "Bearer";  // service, client, user
    protected $uuid;        // respective uuid

    protected $mac_algorithm  = "HS256"; // use only JWA names
    protected $expires_in     = 0;
    protected $max_seq        = 0;
    protected $use_sequence   = false;
    protected $dbKeys;

    protected $tokenLength;
    
    protected $db;

    public function __construct($db, $options=array()) {
        $this->db = $db;

        $this->dbKeys = array(
            "id"            => "INTEGER",
            "access_token"  => "TEXT",
            "refresh_token" => "TEXT",
            "decrypt_key"   => "TEXT",
            "sign_key"      => "TEXT",
            "algorithm"     => "TEXT",
            "userid"        => "INTEGER",
            "authority_id"  => "INTEGER",
            "parent_id"     => "INTEGER",
            "scope"         => "TEXT",
            "expirationdate"=> "INTEGER",
            "consumed"      => "INTEGER",
            "max_seq"       => "INTEGER",
        );

        // this should be set via the options
        $this->tokenLength = array(
            "access_key" => 50,
            "mac_key"    => 100,
            "kid"        => 10
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

            if (array_key_exists("mac_algorithm", $options) &&
                in_array($options["mac_algorithm"], $aAlg)) {

                // if we get an old MAC name, transpose to the new JWA name
                if ($options["mac_algorithm"] == "hmac-sha-256") {

                    $options["mac_algorithm"] = "HS256";
                }
                $this->mac_algorithm = $options["mac_algorithm"];
            }
            if (array_key_exists("alg", $options) &&
                in_array($options["alg"], $aAlg)) {

                if ($options["alg"] == "hmac-sha-256") {

                    $options["alg"] = "HS256";
                }
                $this->mac_algorithm = $options["alg"];
            }

            if (array_key_exists("use_sequence", $options)) {
                $this->use_sequence = $options["use_ssequence"]; // evaluates as Boolean
            }
            if (array_key_exists("max_sequence", $options)) {
                $this->max_seq = $options["max_sequence"];
            }
            if (array_key_exists("type", $options)) {
                $this->token_type = $options["type"];
            }
            if (array_key_exists("token_type", $options)) {
                $this->token_type = $options["token_type"];
            }
        }
    }

    /**
     * @public @function setToken()
     *
     * initialises using a token (provided by the token validator)
     */
    public function setToken($token) {
        $aValid = array("type");
        foreach ($this->dbKeys as $k => $v) {
            $aValid[] = $k;
        }

        $bOK = true;
        foreach (array_keys($token) as $tk) {
            if (!in_array($tk, $aValid)) {
                $bOK = false;
                break;
            }
        }

        if ($bOK){
            if (!array_key_exists('token_type', $token)) {
                $token['token_type'] = $token["type"];
            }

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
            $this->root_token_type = $token["token_type"];
            if (empty($this->token_type)) {

                $this->token_type = $this->root_token_type;
            }
         }
    }

    public function getToken() {
        return $this->token;
    }

    public function getRootToken() {
        return $this->root_token;
    }

    // create a new TM instance with the active token as root
    public function getIssuer($type="") {
        $tm = null;
        if ($this->token) {
            $tm = new Token($this->db, array("type" => $type));
            $tm->setDebugMode($this->getDebugMode());
            $tm->setRootToken($this->token);
        }
        return $tm;
    }

    public function getUser() {
        if (!empty($this->token) &&
            !empty($this->token["user_uuid"])) {

            $um = new User($this->db);
            $um->setDebugMode($this->getDebugMode());
            if ($um->findByUUID($this->token["user_uuid"])) {
                return $um;
            }
        }

        return null;
    }

    /**
     * change the type for new tokens
     */
    public function setTokenType($type="Bearer") {
        if (!empty($type)) {
            $this->token_type = $type;
        }
    }

    public function checkTokenValue($key, $value) {
        if ($this->token &&
            array_key_exists($key, $this->token) &&
            !empty($this->token[$key]) &&
            $this->token[$key] === $value) {

            return true;
        }
        return false;
    }

    public function hasTokenValue($key) {
        if ($this->token &&
            array_key_exists($key, $this->token) &&
            (!empty($this->token[$key]) || $this->token[$key] > 0)) {
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

    public function useSequence() {
        $this->use_sequence = true;
    }

    public function setMaxSeq($maxseq) {
        $this->use_sequence = true;
        if (isset($maxseq) && $maxseq > 0) {
            $this->max_seq = $maxseq;
        }
    }

    /**
     * create a basic token
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
    public function newToken($type="MAC", $fullToken=false) {
        $newToken = array();

        $newToken["access_key"] = $this->randomString($this->tokenLength["access_key"]);
        if ($type == "Bearer" && !$fullToken) {
            $newToken["kid"] = $newToken["access_key"];
        }
        else {
            $newToken["kid"] = $this->randomString($this->tokenLength["kid"]);

            if (isset($this->mac_algorithm) &&
                !empty($this->mac_algorithm)) {
                $newToken["mac_algorithm"] = $this->mac_algorithm;
            }

            $newToken["mac_key"] = $this->randomString($this->tokenLength["mac_key"]);
        }

        if (isset($this->expires_in) &&
            $this->expires_in > 0) {

            $now = time();
            $newToken["expires"] = $now + $this->expires_in;
        }

        return $newToken;
    }

    /**
     * function addToken
     *
     * initialise a token.
     *
     * this function sets external information such as the user_uuid etc.
     *
     * Allowed token values are
     * * type | token_type
     * * user_uuid
     * * client_id
     * * service_uuid
     * * scope
     * * extra
     * * mac_algorithm as a client can set a preference
     *
     * At least one of user_uuid, service_uuid, or client_id MUST be set.
     */
    public function addToken($token=array(), $fullToken=false) {
        $type = $this->token_type;
        if (array_key_exists("type", $token)) {
            $type = $token["type"];
        }
        if (array_key_exists("token_type", $token)) {
            $type = $token["token_type"];
        }

        $newToken = $this->newToken($type, $fullToken);

        if (isset($this->root_token)) {
            if (empty($type)) {
                $type = $this->root_token_type;
            }
            $newToken["parent_kid"] = $this->root_token["kid"];
        }

        if (!empty($type) &&
            (
                (array_key_exists("user_uuid", $token) && !empty($token["user_uuid"])) ||
                (array_key_exists("service_uuid", $token) && !empty($token["service_uuid"])) ||
                (array_key_exists("client_id", $token) && !empty($token["client_id"]))
            )) {

            $newToken["token_type"] = $type;
            if (!$this->use_sequence) {
                $newToken["seq_nr"] = 0;
            }
            else if (isset($this->max_seq) &&
                     $this->max_seq > 0) {
                $newToken["max_seq"] = $this->max_seq;
            }

            foreach(array("user_uuid",
                          "client_id",
                          "service_uuid",
                          "scope",
                          "extra",
                          "mac_algorithm") as $key) {

                // inherit different approaches from the root token
                if (!empty($this->root_token) &&
                    array_key_exists($key, $this->root_token) &&
                    !empty($this->root_token[$key])) {
                    if ($key === "extra") {
                        $newToken[$key] = json_encode($this->root_token[$key]);
                    }
                    else {
                        $newToken[$key] = $this->root_token[$key];
                    }
                }

                if (array_key_exists($key, $token) &&
                    !empty($token[$key])) {

                    if ($key === "extra") {
                        $newToken[$key] = json_encode($token[$key]);
                    }
                    else {
                        $newToken[$key] = $token[$key];
                    }
                }
            }

            $newToken["issued_at"] = time();

            // store the data into the database
            $aNames = array();
            $aValues = array();
            $aPH = array();
            $aTypes = array();

            foreach ( $this->dbKeys as $k => $v) {
                if (array_key_exists($k, $newToken)) {
                    $aTypes[] = $v;
                    $aNames[] = $k;
                    $aValues[] = $newToken[$k];
                    $aPH[] = '?';
                }
            }

            if (!empty($aNames)) {
                $sqlstr = "INSERT INTO tokens (".implode(",", $aNames).") VALUES (".implode(",", $aPH).")";
                $sth = $this->db->prepare($sqlstr, $aTypes);
                $res = $sth->execute($aValues);
                if(\PEAR::isError($res)){
                    $this->log($res->getMessage() . " '" . $sqlstr . "' " . implode(", ", $aValues));
                }
                else {
                    $this->token = $newToken;
                    $this->token["extra"] = json_decode($this->token["extra"], true);
                    if (isset($this->expires_in) &&
                        $this->expires_in > 0) {
                        $this->token["expires_in"] = $this->expires_in;
                    }
                }
                $sth->free();

            }
        }
    }

    public function invalidateToken() {
        $this->consumeToken();
    }

    public function consumeRootToken() {
        if (!empty($this->token)) {
            $this->consume_token_db($this->token["parent_kid"]);
        }
    }

    public function consumeToken() {
        if (!empty($this->token)) {
            $this->consume_token_db($this->token["kid"]);
        }
    }

    // access number of loaded tokens
    public function count() {
        return count($this->token_list);
    }

    public function next() {
        if ($this->active_token >= 0 &&
            $this->active_token < $this->count()) {

            if ($this->token) {
                $this->active_token++;
            }
            if($this->active_token < $this->count()) {
                $this->token = $this->token_list[$this->active_token];
                return $this->getToken();
            }
        }
        return null;
    }

    public function findTokens($options) {
        $aDBFields  = array_keys($this->dbKeys);
        $aTypes     = array();
        $aValues    = array();
        $condition  = array();

        $this->token_list  = array();
        $this->active_token = -1;
        $this->token = null;

        if (!empty($options)) {
            foreach ($options as $f => $c) {
                if (array_key_exists($f, $this->dbKeys)) {
                    $aTypes[]    = $this->dbKeys[$f];
                    $aValues[]   = $c;
                    $condition[] = "$f = ?";
                }
            }
         }

        if (!empty($aTypes)) {
            if (!array_key_exists("consumed", $options)) {
                $aTypes[] = $this->dbKeys["consumed"];
                $aValues[] = 0;
                $condition[] = "consumed = ?";
            }

            $sqlstr = "select " . implode(", ", $aDBFields). " from tokens where " . implode(" AND ", $condition);

            $sth = $this->db->prepare($sqlstr, $aTypes);
            $res = $sth->execute($aValues);

            if (\PEAR::isError($res)) {
                $this->log($res->getMessage());
            }
            else {
                while ($row = $res->fetchRow(MDB2_FETCHMODE_ASSOC)) {
                    $token = array();
                    foreach ($row as $key => $value) {
                        if ($key === "extra") {
                            $token[$key] = json_decode($value, true);
                        }
                        else {
                            $token[$key] = $value;
                        }
                    }

                    $this->token_list[] = $token;
                }
            }
            $sth->free();

            if (!empty($this->token_list)) {
                $this->active_token = 0;
            }
        }
        return $this->token_list;
    }

    public function findToken($token_id, $type="", $userUuid="", $active=true) {

        $cond = array("kid"=> $token_id);
        if (!empty($type)) {
            $cond["token_type"] = $type;
        }
        if (!empty($userUuid)) {
            $cond["user_uuid"] = $userUuid;
        }
        if (!$active) {
            $cond["consumed"] = 1;
        }

        $this->findTokens($cond);

        return ($this->count() > 0);
    }

    public function findRootToken() {
        $aDBFields = array_keys($this->dbKeys);

        if (!empty($this->token) &&
            array_key_exists("parent_kid", $this->token) &&
            !empty($this->token["parent_kid"])) {

            $sqlstr = "select ".implode(", ", $aDBFields)." from tokens where and token_id = ?";

            $aTypes   = array("TEXT");
            $aValues  = array($this->token["parent_kid"]);

            $sth = $this->db->prepare($sqlstr, $aTypes);
            $res = $sth->execute($aValues);

            $this->root_token = null;
            $this->root_token = array();

            if ($row = $res->fetchRow(MDB2_FETCHMODE_ASSOC)) {

                foreach ($row as $key => $value) {
                    if ($key === "extra") {
                        $this->root_token[$key] = json_decode($value, true);
                    }
                    else {
                        $this->root_token[$key] = $value;
                    }
                }
            }

            $sth->free();
        }
    }

    /**
     * use this function if you need to add a different token information
     */
    public function prepareSubToken($type) {
        if (!empty($type) &&
            !empty($this->token)) {

            $tm = new Token($this->db);
            $tm->setDebugMode($this->getDebugMode());
            
            $tm->setRootToken($this->token);
            $tm->setTokenType($type);

            return $tm;
        }
        return null;
    }

    /**
     * shortcut for Refresh Tokens
     */
    public function addSubToken($type) {
        $tm = $this->prepareSubToken($type);
        if ($tm) {
            $tm->addToken(array());
        }
        return $tm;
    }

    public function eraseToken() {
        if (!empty($this->token)) {
            $sqlstr = "DELETE FROM tokens WHERE kid = ?";
            $sth = $this->db->prepare($sqlstr, array("TEXT"));
            $res = $sth->execute(array($this->token["kid"]));
            if (\PEAR::isError($res)) {
                $this->log($res->getMessage());
            }
            $sth->free();
        }
    }

    private function consume_token_db($key) {
        $sqlstr = "update tokens set consumed = ? where token_key = ?";
        $sth = $this->db->prepare($sqlstr, array("INTEGER", "TEXT"));

        $now = time();

        if (\PEAR::isError($sth)) {
            $this->log($sth->getMessage() . " ". $sqlstr);
        }
        $res = $sth->execute(array($now,
                                   $key));

        if (\PEAR::isError($res))
        {
            $this->error = $res->getMessage();
        }
        $sth->free();

        // consume all children
        $sqlstr = "update table tokens set consumed = ? where token_parent = ? and token_type <> 'Refresh'";

        $sth = $this->db->prepare($sqlstr, array("INTEGER", "TEXT"));
        $res = $sth->execute(array($now,
                                   $key));

        if (\PEAR::isError($res))
        {
            $this->error = $res->getMessage();
        }
        $sth->free();
    }

    public function sequenceStep() {
        if ($this->token && $this->use_sequence) {
            $sqlstr = "update  tokens set seq_nr = ? where kid = ?";
            $sth = $this->db->prepare($sqlstr, array("INTEGER", "TEXT"));

            $res = $sth->execute(array($this->token["seq_nr"] + 1,
                                       $this->token["kid"]));

            if (\PEAR::isError($res))
            {
                $this->error = $res->getMessage();
                $this->log($res->getMessage());
            }
            $sth->free();
        }
    }

    public function updateAccess() {
        $sqlstr = "update  tokens set last_access = ? where kid = ?";
        $sth = $this->db->prepare($sqlstr, array("INTEGER", "TEXT"));

        $res = $sth->execute(array(time(),
                                   $this->token["kid"]));

        if (\PEAR::isError($res))
        {
            $this->error = $res->getMessage();
            $this->log($res->getMessage());
        }
        $sth->free();
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
