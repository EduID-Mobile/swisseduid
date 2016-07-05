<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */
namespace EduID\Model;

//require_once($CFG->libdir . "/adminlib.php");
//require_once($CFG->libdir . "/filelib.php");
require_once("tag/lib.php");
require_once("user/editlib.php");
require_once("user/profile/lib.php");
require_once("user/lib.php");


class User extends ModelFoundation {
    private $user;

    public function hasUser() {
        return isset($this->user);
    }

    public function userId() {
        return $this->user->id;
    }

    public function updateUserInfo($user) {
        if ($this->findUser($user->email)) {
            $this->mark(" + ");
            $this->updateUser($user);
        }
        else {
            $this->mark(" - ");
            $this->createUser($user);
        }
    }

    private function findUser($usermail) {
        global $DB;
        $this->log($usermail);
        $this->user = $DB->get_record('user', array('email' => $usermail));
        if ($this->user) {
            $this->mark();
        }

        return $this->hasUser();
    }

    private function createUser($user) {
        global $CFG, $DB;

        $usernew = new \stdClass();

        $usernew->auth      = "OAuth2";
        $usernew->username  = $user->email;
        $usernew->email     = $user->email;
        $usernew->firstname = $user->firstname;
        $usernew->lastname  = $user->lastname;
        // $usernew->idnumber  = $user->id;

        $usernew->confirmed = 1;
        $usernew->interests = "";

        // Moodle wants more for valid users.
        $usernew->timecreated = time();
        $usernew->mnethostid = $CFG->mnet_localhost_id; // Always local user.
        $usernew->password = AUTH_PASSWORD_NOT_CACHED;  // because the authority is elsewhere

        $this->log("create user");
        $usernew->id = \user_create_user($usernew, false, false);

        if ($usernew->id > 0)
        {
            // moodle wants additional profile setups
            $usercontext = \context_user::instance($usernew->id);

            // Update preferences.
            useredit_update_user_preference($usernew);

            if (!empty($CFG->usetags)) {
                useredit_update_interests($usernew, $usernew->interests);
            }

            // Update mail bounces.
            useredit_update_bounces($usernew, $usernew);

            // Update forum track preference.
            useredit_update_trackforums($usernew, $usernew);

            // Save custom profile fields data.
            profile_save_data($usernew);

            // Reload from db.
            $usernew = $DB->get_record('user', array('id' => $usernew->id));

            // not sure what this will do, but moodle wants it.
            \core\event\user_created::create_from_userid($usernew->id)->trigger();

            $createUser++;
            return $usernew;
        }
        return null;
    }

    private function updateUser($user) {
        foreach (get_object_vars($user) as $k => $v) {

            $this->user->$k = $user->$k;
        }

        $this->log("update user");
        user_update_user($this->user, false, false);
    }
}

?>
