<?php
require_once('../../config.php');
require_once($CFG->libdir.'/authlib.php');
require_once($CFG->libdir.'/externallib.php');
require_once('lib.php');

header('Content-type: application/json');
$eduid_auth = get_auth_plugin('eduid');
$headers = null;

// return true if everything is ok otherwise returns the error code
function params_valid() {
	/* return true; */
	global $headers;
	$headers = getallheaders();
	// parameters needed are access_token and service_shortname
	$access_token_valid = isset($headers['Authorization']) and !empty($headers['Authorization']);
	$service_shortname_valid = isset($_GET['service_shortname']) and !empty($_GET['service_shortname']);

	// check the other parameters
	if( !$access_token_valid ) {
		return 3;
	} else if( !$service_shortname_valid) {
		return 4;
	} else {
		return true;
	}
}

// this function extracts the valid token from the database;
// if a valid token is not available then a new one is generated
function get_valid_external_token($service, $userid, $context, $validuntil) {
	global $DB;
	$entry = $DB->get_records('external_tokens', array('externalserviceid'=>$service->id, 'userid'=>$userid, 'contextid' => $context->id));
	$entryid = key($entry);
	$entry = reset($entry);
	if(empty($entry) || $entry->validuntil < time()) {
		$DB->delete_records('external_tokens', array('id' => $entryid));
		return external_generate_token(EXTERNAL_TOKEN_PERMANENT, $service->id, $userid, $context, $validuntil);
	} else {
		return $entry->token;
	}
}

// check the parameters
$params_check = params_valid();

if( $params_check === true ) {
	// check the service access token first
	$service_access_record = $DB->get_record('auth_eduid_tokens', array('access_token' => $headers['Authorization']));
	/* $service_access_record = $DB->get_record('auth_eduid_tokens', array('access_token' => 'f4bb00f0061360a9cf2359598a1e840f77242b5f')); */
	if($service_access_record === false || $service_access_record->expiration < time()) {
		echo json_encode( $eduid_auth->error(3) ); return;
	}

	// check if the service shortname is in the external_services table
	$service = $DB->get_record('external_services', array('shortname' => $_GET['service_shortname']));
	if($service === false) {
		echo json_encode( $eduid_auth->error(4) ); return;
	} else {
		// get the system context
		$context = context_system::instance();
		// get the moodle external token tied to the used service
		$external_token_record = $DB->get_record('external_tokens', array('userid' => $service_access_record->userid, 'externalserviceid' => $service->id));
		// initialize the token and expiration date
		$token = '';
		$expires_in = '';
		// if the external token is valid return it. Otherwise generate a new one.
		if($external_generate_token === false || $external_token_record->validuntil < time()) {
			// let the external token have the same expiration date as the access_token
			$expiration_date = $service_access_record->expiration;
			/* $expiration_date = time() + $eduid_auth->config->service_token_expiration_date; */
			$token = get_valid_external_token($service, $service_access_record->userid, $context, $expiration_date);
			$expires_in = $expiration_date - time();
		} else {
			$token = $external_token_record->token;
			$expires_in = $external_token_record->validuntil - time();
		}
		// output the token information
		echo json_encode(array(
			'token' => $token,
			'expires_in' => $expires_in
		));
	}
} else {
	echo json_encode( $eduid_auth->error($params_check) );
}
?>
