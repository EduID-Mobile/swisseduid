<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */
namespace EduID\Model;

//require_once($CFG->libdir . "/adminlib.php");
//require_once($CFG->libdir . "/filelib.php");
global $CFG;
require_once("$CFG->dirroot/tag/lib.php");
require_once("$CFG->dirroot/user/editlib.php");
require_once("$CFG->dirroot/user/profile/lib.php");
require_once("$CFG->dirroot/user/lib.php");


class User extends ModelFoundation {
    private $user;

    public function hasUser() {
        return (isset($this->user) && $this->user);
    }

    public function userId() {
        return $this->user->id;
    }

    public function updateUserInfo($user) {
        if ($this->findUser($user->email)) {
            $this->log(" update user from assertion ");
            $this->updateUser($user);
        }
        else {
            $this->log(" create user from assertion ");
            $this->createUser($user);
        }
    }

    private function findUser($usermail) {
        global $DB;
        $this->user = $DB->get_record('user', array('email' => $usermail));

        return $this->hasUser();
    }

    private function createUser($user) {
        global $CFG, $DB;

        $usernew = new \stdClass();

        $usernew->auth      = "eduid";
        $usernew->username  = $user->username;
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
            \profile_save_data($usernew);

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
        if ($user) {

            foreach (get_object_vars($user) as $k => $v) {
                $this->user->$k = $user->$k;
            }

            user_update_user($this->user, false, false);
        }
        else {
            $this->log("empty user object");
        }
    }
}

?>
