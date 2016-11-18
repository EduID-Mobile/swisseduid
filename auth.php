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
        global $CFG, $DB;
		$authority_entries = $DB->get_records('auth_eduid_authorities');
        include "config.php";
    }

    /**
     * Processes and stores configuration data for this authentication plugin.
     *
     *
     * @param object $config Configuration object
     */
    function process_config($config) {
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

		if( !$this->updateAuthorityEntries($config->authority) ) {
			return false;
		}

		if( !$this->addAuthorityEntries($config->new_authority) ) {
			return false;
		}

		return false;
    }

	function addAuthorityEntries($entries) {
        global $CFG, $DB;
		// add new entries
		foreach($entries as $num => $entry) {
			if(
				empty($entry["authority_name"]) ||
				empty($entry["authority_url"]) ||
				empty($entry["authority_shared_token"]) ||
				empty($entry["privkey_for_authority"]) ||
				empty($entry["authority_public_key"])
			) {
				print_r('ignoring the following');
				print_r($entry);
				continue; // ignore incomplete entries
			} else {
				$DB->insert_record('auth_eduid_authorities',
					array(
						"authority_name" => $entry["authority_name"],
						"authority_url" => $entry["authority_url"],
						"authority_shared_token" => $entry["authority_shared_token"],
						"privkey_for_authority" => $entry["privkey_for_authority"],
						"authority_public_key" => $entry["authority_public_key"]
					)
				);
			}
		}

		return true;
	}

	function updateAuthorityEntries($entries) {
        global $CFG, $DB;
		// purge the deleted entries
		$current_authority_entries = $DB->get_records('auth_eduid_authorities');
		foreach($current_authority_entries as $authority) {
			if(!array_key_exists($authority->id, $entries)) {
				$DB->delete_records('auth_eduid_authorities', array('id' => $authority->id));
			}
		}
		// update the rest
		$current_authority_entries = $DB->get_records('auth_eduid_authorities');
		foreach($current_authority_entries as $authority) {
			/* print_r($content); continue; */
			if(
				$authority->authority_name != $entries[$authority->id]["authority_name"] ||
				$authority->authority_url != $entries[$authority->id]["authority_url"] ||
				$authority->authority_shared_token != $entries[$authority->id]["authority_shared_token"] ||
				$authority->privkey_for_authority != $entries[$authority->id]["privkey_for_authority"] ||
				$authority->authority_public_key != $entries[$authority->id]["authority_public_key"]
			) {
				$DB->update_record('auth_eduid_authorities',
					array(
						"id" => $authority->id,
						"authority_name" => $entries[$authority->id]["authority_name"],
						"authority_url" => $entries[$authority->id]["authority_url"],
						"authority_shared_token" => $entries[$authority->id]["authority_shared_token"],
						"privkey_for_authority" => $entries[$authority->id]["privkey_for_authority"],
						"authority_public_key" => $entries[$authority->id]["authority_public_key"]
					)
				);
			}
		}

		return true;
	}

	function create_user_session($userid) {
		// check if the user is valid
		$USER = $DB->get_record('user', array('username' => $_POST['username']) );
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


