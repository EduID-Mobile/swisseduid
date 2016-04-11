<?php
// this function fetches the user
// picture url
function __get_link($userpicture){
    global $OUTPUT, $PAGE, $USER;

    if (empty($userpicture->size)) {
        $file = 'f2';
        $size = 35;
    } else if ($userpicture->size === true or $userpicture->size == 1) {
        $file = 'f1';
        $size = 100;
    } else if ($userpicture->size >= 50) {
        $file = 'f1';
        $size = $userpicture->size;
    } else {
        $file = 'f2';
        $size = $userpicture->size;
    }

    $class = $userpicture->class;
    $user = $userpicture->user;
    $usercontext = context_user::instance($USER->id);
    if ($user->picture == 1) {
        $usercontext = context_user::instance($user->id);
        $src = moodle_url::make_pluginfile_url($usercontext->id, 'user', 'icon', NULL, '/', $file);
    } else if ($user->picture == 2) {
        //TODO: gravatar user icon support
    } else { // Print default user pictures (use theme version if available)
        $PAGE->set_context($usercontext);
        $src = $OUTPUT->pix_url('u/' . $file);
    }

    return $src->out();
}

function __extract_last_user_access_to_the_course($userid, $courseid) {
	global $DB;

	$lastaccesses = $DB->get_records('user_lastaccess', array('userid'=>$userid, 'courseid' => $courseid));
	if(empty($lastaccesses)) {
		return 0;
	} else {
		// get the timeacces; it is one of the values of the first entry in the $lastaccesses array
		return intval(reset($lastaccesses)->timeaccess);
	}
}

?>
