<?php
/*
 *
 */
namespace EduID;

use EduID\Validator\Header\Token as TokenValidator;

require_once('MDB2.php');

/**
 *
 */
class ServiceFoundation extends \RESTling\Service {

    protected $db;
    protected $configuration;

    protected $tokenValidator;

    public function __construct() {
        // init the service
        parent::__construct();
        $this->setDebugMode(false);
        
        // always strip the first path_info because the services are called via eduid.php
        array_shift($this->path_info);

        $this->initDataBase();
        $this->initSessionValidator();
    }

    public function getTokenUser() {
        return $this->tokenValidator->getTokenUser();
    }

    protected function getConfiguration($key) {

        if (isset($this->configuration) &&
            array_key_exists($key, $this->configuration)) {

            return $this->configuration[$key];
        }

        return null;
    }

    private function initDatabase() {
       // fetch global value from moodle
    }

    public function getDataBase() {
        return $this->db;
    }

    private function initSessionValidator() {
        if ($this->status == \RESTling\Service::OK) {
//            $sessionValidator = new SessionValidator($his->db);

            $this->tokenValidator   = new TokenValidator($this->db);
            $this->tokenValidator->setDebugMode($this->getDebugMode());

            $this->addHeaderValidator($this->tokenValidator);

            // by default we accept only MAC tokens
            $this->tokenValidator->resetAcceptedTokens(array("MAC"));
        }
    }
}

?>
