<?php

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->libdir.'/authlib.php');

class auth_plugin_eduid extends auth_plugin_base {

    /**
     * Constructor.
     */
    function auth_plugin_eduid() {
        $this->authtype = 'eduid';
		$this->config = get_config('auth/eduid');
    }

    function user_login($username, $password) {
		$manual_auth = get_auth_plugin('manual');
        return $manual_auth->user_login($username, $password);
    }

	/**
     * Rules for the plugins
     *
     * @return bool
     */
	function can_signup() { return false; }
	function can_edit_profile() { return false; }
	function can_confirm() { return false; }
	function can_be_manually_set() { return false; }

    /**
     * Prints a form for configuring this authentication plugin.
     *
     * This function is called from admin/auth.php, and outputs a full page with
     * a form for configuring this plugin.
     *
     * @param array $page An object containing all the data for this page.
     */
    function config_form($config, $err, $user_fields) {
        include "config.php";
    }

    /**
     * Processes and stores configuration data for this authentication plugin.
     *
     *
     * @param object $config Configuration object
     */
    function process_config($config) {
        global $CFG;
		
        if(empty($config->eduid_user_info_endpoint)) {
			set_config('', $config->eduid_user_info_endpoint, 'auth/eduid');
		} else {
			set_config('eduid_user_info_endpoint', $config->eduid_user_info_endpoint, 'auth/eduid');
		}

        if( isset($config->service_token_duration) and is_numeric($config->service_token_duration)) {
			set_config('service_token_duration', $config->service_token_duration, 'auth/eduid');
		} else {
			return false;
		}

        if( isset($config->app_token_duration) and is_numeric($config->app_token_duration)) {
			set_config('app_token_duration', $config->app_token_duration, 'auth/eduid');
		} else {
			return false;
		}

		return true;
    }

	function create_user_session($userid) {
		// check if the user is valid
		$USER = $DB->get_record('user', array('username'=>$_POST['username']) );
		if(empty($USER)) {
			return $this->error(2);
		} else {
			return true;
		}
	}

	function error($code) {
		$error = array(
				'exception' => 'unknown exception',
				'message' => 'no message',
				'debuginfo'=>'code: '.$code
				);

		switch ($code) {
			case 0:
				$error["exception"] = 'authentication_failed';
				$error["message"] = 'Authentication failed. Authorization code not valid.';
				break;
			case 1:
				$error["exception"] = 'grant_type_exception';
				$error["message"] = 'The grant_type is not valid!';
				break;
			case 2:
				$error["exception"] = 'code_exception';
				$error["message"] = 'The code is not valid!';
				break;
			case 3:
				$error["exception"] = 'access_token_exception';
				$error["message"] = 'The access token is not valid!';
				break;
			case 4:
				$error["exception"] = 'service_shortname_exception';
				$error["message"] = 'The service shortname is not valid!';
				break;
			case 5:
				$error["exception"] = 'unknown_user';
				$error["message"] = 'The selected user has never accessed the website';
				break;
			case 6:
				$error["exception"] = 'invalid_request';
				$error["message"] = 'The revoke request is not valid. Please provide the token and token_type_hint parameters. token_type_hint should be access_token or refresh_token.';
				break;
			case 7:
				$error["exception"] = 'revoking_permission_not_valid';
				$error["message"] = 'The current token does not belong to the authenticated user.';
				break;
			case 8:
				$error["exception"] = 'revoking_not_necessary';
				$error["message"] = 'The authenticated user has no tokens to revoke.';
				break;
			default:
				$error["exception"] = 'unknown_error_exception';
				$error["message"] = 'Unknown server error!';
				break;
		}
		return $error;
	}
}


