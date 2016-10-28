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
	// store the service access token in the database
	global $DB;

	$output = request( $eduid_auth->config->eduid_user_info_endpoint, $headers['Authorization'] );
	$user_info = json_decode($output);

	/* $user_info = new stdClass(); */
	/* $user_info->uniqueID = 'goran.student'; */

	if(empty($_POST['token']) || empty($_POST['token_type_hint'])) {
		echo json_encode( $eduid_auth->error(6) ); return;
	} else {
		$service_access_record = $DB->get_record('auth_eduid_tokens', array('access_token' => $headers['Authorization']));
		// get the user data
		$user = $DB->get_record('user', array('username' => $user_info->uniqueID), 'id' );

		if(empty($user)) {
			echo json_encode( $eduid_auth->error(5) ); return;
		}

		// get the current token record
		$current_token_record = $DB->get_record('auth_eduid_tokens', array('userid' => $user->id));
		if($current_token_record === false) {
			echo json_encode( $eduid_auth->error(8) ); return;
		} else {
			// revoke the hinted token
			if($_POST['token_type_hint'] == 'access_token') {
				// verify the token is the same as the one stored currently
				if($current_token_record->access_token == $_POST['token']) {
					$current_token_record->access_token = '';
				} else {
					echo json_encode( $eduid_auth->error(7) ); return;
				}
			} else if($_POST['token_type_hint'] == 'refresh_token') {
				// verify the token is the same as the one stored currently
				if($current_token_record->refresh_token == $_POST['token']) {
					$current_token_record->refresh_token = '';
				} else {
					echo json_encode( $eduid_auth->error(7) ); return;
				}
			} else {
				// the token_type_hint doesn't contain 'access_token' or 'refresh_token'.
				// this is malformed request. 
				echo json_encode( $eduid_auth->error(6) ); return;
			}	
			if($DB->update_record('auth_eduid_tokens', $current_token_record)) {
				// success
				http_response_code(200);
			} else {
				// unknown internal server; probably database problem.
				http_response_code(500);
			}
		}
	} 
} else {
	echo json_encode( $eduid_auth->error(2) );
}
?>
