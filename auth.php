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
        include "config.html";
    }

    /**
     * Processes and stores configuration data for this authentication plugin.
     *
     *
     * @param object $config Configuration object
     */
    function process_config($config) {
        global $CFG;
		
        if(empty($config->service_id)) {
			set_config('', $config->service_id, 'auth/eduid');	
		} else {
			set_config('service_id', $config->service_id, 'auth/eduid');	
		}

        if( isset($config->token_duration) and is_numeric($config->token_duration)) {
			set_config('token_duration', $config->token_duration, 'auth/eduid');	
		} else {
			return false;	
		}

		return true;
    }

	public function error($code) {
		$error = array(
				'exception' => 'unknown exception',
				'message' => 'no message',
				'debuginfo'=>'code: '.$code
				);

		switch ($code) {
			case 0:
				$error["exception"] = 'authentication_failed';
				$error["message"] = 'Authentication failed. Please check your username and password.';
				break;
			case 1:
				$error["exception"] = 'user_data_exception';
				$error["message"] = 'The username is missing.';
				break;
			case 2:
				$error["exception"] = 'user_data_exception';
				$error["message"] = 'The password is missing.';
				break;
		}
		return $error;
	}
}


