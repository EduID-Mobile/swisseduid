<?php

namespace EduID;
use RESTling\Logger; 

use Lcobucci\JWT as JWT;

class Curler extends Logger {
    private $curl;
    private $protocol;
    private $host;
    private $base_url;
    private $path_info;

    private $param;
    private $body;

    private $status;
    private $out_header;
    private $in_header;
    private $mac_token;
    private $token_type = "mac";
    private $jwtClaims = array();

    private $next_url;
    private $next_method;

    public function __construct($options="") {
        if (empty($options)) {
                $options = array();
        }
        $this->setUrl($options);
        $this->param = array();
        $this->out_header = array();
    }
    
    public function setUrl($options) {
        if (!empty($options)) {
            if (is_string($options)) {
                $options = parse_url($options);
            }
            $this->protocol   = array_key_exists("scheme", $options)    ? $options["scheme"]    : "http";
            $this->host       = array_key_exists("host", $options)      ? $options["host"]      : "";
            $this->base_url   = array_key_exists("path", $options)      ? $options["path"]      : "";
            $this->path_info  = array_key_exists("path_info", $options) ? $options["path_info"] : "";
        }
    }
    
    public function useMacToken() {
        $this->token_type = "mac";
    }

    public function useJwtToken($claims = "") {
        $this->token_type = "jwt";
        $this->jwtClaims = array();
        if (!empty($claims) && is_array($claims)) {
            $this->jwtClaims = $claims;
        }
    }
    
    public function setPath($path) {
        if (!empty($path) && is_string($path)) {
            $this->base_url = $path;
        }
        else {
            $this->base_url = "/";
        }
    }

    public function setPathInfo($pi="") {
        $this->path_info = $pi;
    }

    public function setGetParameter($p) {
        $this->param = $p;
    }

    public function getLastUri(){
        return $this->next_url;
    }
    public function setHeader($p) {
        $this->out_header = $p;
    }
    
    public function resetHeader() {
        $this->out_header = null;
    }

    public function setToken($t) {
        $this->mac_token = $t;
    }

    private function prepareUri($data="") {
        $this->path_info = ltrim($this->path_info, "/");
        $this->next_url  = $this->protocol . "://" . $this->host . $this->base_url;
        if (!empty($this->path_info)) {
            $this->next_url .= "/" . $this->path_info;
        }

        $this->next_url .= $this->prepareQueryString($data);
    }

    private function getSigner($alg) {
        $signer = null;

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

    private function prepareOutHeader($type="") {
        $th = array();

        if (!empty($type)) {
            array_push($th, "Content-Type: " . $type);
        }

        $authType = "prepare_auth_" . $this->token_type;
        
        if (method_exists($this, $authType)) {
            $h = call_user_func(array($this,$authType));
            if (!empty($h)) {
                $th[] = $h;
            }
        }

        if (!empty($this->out_header)) {
            foreach ($this->out_header as $k => $v) {
                $th[] = $k . ": " . $v;
            }
        }
        if (!empty($th)) {            
            curl_setopt($this->curl, CURLOPT_HTTPHEADER, $th);
        }
    }

    private function prepare_auth_mac() {
        $header = "";

        $this->mark();
        if (!empty($this->mac_token)) {
            $oUri= parse_url($this->next_url);
            // sign mac token
            $signer = $this->getSigner($this->mac_token["mac_algorithm"]);

            // generate payload
            $ts = time();

            $payload = $this->next_method . " " . $oUri["path"];
            if (array_key_exists("query", $oUri)) {
                $payload .= "?". $oUri["query"];
            }
            $payload .= " HTTP/1.1\n";
            $payload .= "$ts\n";
            $payload .= $oUri["host"] . "\n";

            $authHeader = array(
                "kid=" . $this->mac_token["kid"],
                "ts=$ts",
            );

            $authHeader[] = "mac=" . base64_encode($signer->sign($payload, $this->mac_token["mac_key"]));

            $header = "Authorization: MAC " . implode(',', $authHeader);
        }

        return $header;
    }

    private function prepare_auth_jwt() {
        $header = "";
        $this->mark();
        if (!empty($this->mac_token)) {
            $jwt = new JWT\Builder();

            $jwt->setIssuer($this->mac_token["client_id"]);
            $jwt->setAudience($this->next_url);

            $jwt->setHeader("kid", $this->mac_token["kid"]);

            if (!empty($this->jwtClaims)) {
                foreach ($this->jwtClaims as $c => $v) {
                    if ($c == "subject" || $c == "sub") {
                        $jwt->setSubject($v);
                    }
                    else {
                        $jwt->set($c, $v);

                    }
                }
            }


            $signer = $this->getSigner($this->mac_token["mac_algorithm"]);

            $jwt->sign($signer,
                       $this->mac_token["mac_key"]);

            $t = $jwt->getToken();

            $header = "Authorization: Bearer $t";
        }


        return $header;
        
        if (!empty($th)) {
            
            curl_setopt($this->curl, CURLOPT_HTTPHEADER, $th);
        }
    }

    private function request() {
        $res = curl_exec($this->curl);

        $this->in_header = array();

        $this->in_header["content_type"] = curl_getinfo($this->curl, CURLINFO_CONTENT_TYPE);
        $this->status = curl_getinfo($this->curl, CURLINFO_RESPONSE_CODE);

        $this->body = $res;
    }

    private function prepareQueryString($data) {
        $qs = "";
        $aQ = array();

        if (!empty($data) && is_array($data)) {
            foreach ($data as $k => $v) {
                $aQ[] = urlencode($k) . "=" . urlencode($v);
            }
        }
        if (!empty($this->param) && is_array($this->param)) {
            foreach ($this->param as $k => $v) {
                $aQ[] = urlencode($k) . "=" . urlencode($v);
            }
        }
        if (!empty($aQ)) {
            $qs = implode("&",$aQ);
            if (!empty($qs)) {
                $qs = "?$qs";
            }
        }

        return $qs;
    }
    
    private function prepareRequest() {
        if ($this->curl) {
            curl_close($this->curl);
        }
        
        $this->log($this->next_url);
        $c = curl_init($this->next_url);
        
        curl_setopt($c, CURLOPT_VERBOSE, $this->getDebugMode());
//        curl_setopt($c, CURLOPT_FORBID_REUSE, true);
//        curl_setopt($c, CURLOPT_FRESH_CONNECT, true);
        
        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($c, CURLOPT_CUSTOMREQUEST, $this->next_method );
        
        $this->curl = $c;
    }

    public function get($data="") {
        $this->next_method = "GET";
        $this->prepareUri($data);    
        $this->prepareRequest();

        // curl_setopt($c, CURLOPT_HEADER, true);
        $this->prepareOutHeader();

        $this->request();
    }

    public function post($data, $type) {
        $this->next_method = "POST";
        $this->prepareUri();   
        $this->prepareRequest();
        $this->prepareOutHeader($type);

        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $data);

        $this->request();
    }

    public function put($data, $type) {
        $this->next_method = "PUT";
        $this->prepareUri();   
        $this->prepareRequest();
        $this->prepareOutHeader($type);

        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $data);

        $this->request();
    }


    public function delete($data=""){
        $this->next_method = "DELETE";
        $this->prepareUri($data);
        $this->prepareRequest();
        $this->prepareOutHeader();

        $this->request();
    }

    public function getStatus() {
        return $this->status;
    }

    public function getHeader() {
        return $this->in_header;
    }

    public function getBody() {
        return $this->body;
    }

    public function getUrl() {
        $this->prepareUri();
        return $this->next_url;
    }
}
?>
