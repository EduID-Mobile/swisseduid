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

/* use Lcobucci\JWT as JWT; */

/**
 *
 */
class Revoke extends ServiceFoundation {
    private $userValidator;
    private $serviceManager;

    public function __construct() {
        parent::__construct();

        $this->setOperationParameter("token_type_hint");

        $this->tokenValidator->resetAcceptedTokens(["Bearer"]);
        $this->tokenValidator->ignoreOperations(["post_access_token"]);
        $this->tokenValidator->ignoreOperations(["post_refresh_token"]);
    }

    protected function prepareOperation() {
        $this->mark();
        if ($this->inputData && array_key_exists("token", $this->inputData)) {
			// accept only refresh_token and access_token as token_type_hint values
			if( $this->inputData["token_type_hint"] == 'refresh_token' || $this->inputData["token_type_hint"] == 'access_token') {
        		parent::prepareOperation();
			}
		} else {
        	$this->forbidden(json_encode(["error"=>"invalid_request"])); // the token parameter is missing
        }

    }

    protected function post_access_token() {
		global $DB;
        $this->mark('revoking access token');
		// revoke the access token and the refresh token
        $result = $DB->delete_records('auth_eduid_tokens', array('access_token' => $this->inputData['token']));
		if($result == true) {
			$this->response_code = 200;
		}
    }

    protected function post_refresh_token() {
		global $DB;
        $this->mark('revoking refresh token');
		// revoke the refresh token but leave the access token
        $entry = $DB->get_record('auth_eduid_tokens', array('refresh_token' => $this->inputData['token']));
		$this->mark($entry->id);
        $result = $DB->update_record('auth_eduid_tokens', array('id' => $entry->id, 'refresh_token' => ''));
		if($result == true) {
			$this->response_code = 200;
		}
    }
}

?>
