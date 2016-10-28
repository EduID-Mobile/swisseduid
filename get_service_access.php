<?php
require_once('../../config.php');
require_once($CFG->libdir.'/authlib.php');
require_once($CFG->libdir.'/externallib.php');
require_once('lib.php');

header('Content-type: application/json');
$eduid_auth = get_auth_plugin('eduid');
$headers = getallheaders();

// The needed parameter is the code passed in the http header as Authorization.
if( isset($headers['Authorization']) and !empty($headers['Authorization']) ) {
/* if( true ) { */
	$output = request( $eduid_auth->config->eduid_user_info_endpoint, $headers['Authorization'] );
	$user_info = json_decode($output);

	/* $user_info = new stdClass(); */
	/* $user_info->uniqueID = 'goran.student'; */

	// generate the service access token, very simple for now. Missing the device information.
	$access_token = sha1($user_info->uniqueID . random_string() . time());
	$refresh_token = sha1($user_info->uniqueID . random_string() . time());


	// store the service access token in the database
	global $DB;

	// get the user data
	$user = $DB->get_record('user', array('username' => $user_info->uniqueID), 'id' );

	if(empty($user)) {
		echo json_encode( $eduid_auth->error(5) ); return;
	}

	// check for a previous valid record
	$previous_token_record = $DB->get_record('auth_eduid_tokens', array('userid' => $user->id));
	if($previous_token_record === false) {
		// prepare the new record
		$record = new stdClass();
		$record->userid = $user->id;
		$record->access_token = $access_token;
		$record->refresh_token = $refresh_token;
		$record->expiration = time() + $eduid_auth->config->service_token_duration;
		$record->expires_in = $eduid_auth->config->service_token_duration;
		$DB->insert_record('auth_eduid_tokens', $record);
		$previous_token_record = $DB->get_record('auth_eduid_tokens', array('userid' => $user->id));
	} elseif($previous_token_record->expiration < time()) {
		$previous_token_record->access_token = $access_token;
		$previous_token_record->expiration = time() + $eduid_auth->config->service_token_duration;
		$DB->update_record('auth_eduid_tokens', $previous_token_record);
	} else {
		$access_token = $previous_token_record->access_token;
		$refresh_token = $previous_token_record->refresh_token;
	}
	// give the service access token as output
	echo json_encode(array(
		'token_type' => 'bearer',
		'access_token' => $previous_token_record->access_token,
		'expires_in' => intval($previous_token_record->expires_in),
		'refresh_token' => $previous_token_record->refresh_token
	));
} else {
	echo json_encode( $eduid_auth->error(2) );
}
?>
