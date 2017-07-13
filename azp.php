<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Swiss edu-ID authentication plugin.
 *
 * This script is part of the configuration of the plugin. It allows to
 * manage key chains of authorization servers.
 *
 * @package   auth_swisseduid
 * @copyright 2017 Christian Glahn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
// require_once('/var/www/html/moodle/config.php'); // development only, due to symlinks
require_once($CFG->libdir.'/adminlib.php');

$auth = required_param('auth', PARAM_PLUGIN);
$PAGE->set_pagetype('admin-auth-' . $auth);

admin_externalpage_setup('authsetting'.$auth);

$authplugin = get_auth_plugin($auth);
$err = array();

// When we return, we return to the auth mainpage from here.
$returnurl = "$CFG->wwwroot/$CFG->admin/auth_config.php?auth=$auth";

// Save configuration changes.
if ($frm = data_submitted() and confirm_sesskey()) {

    if (count($err) == 0) {
		$authplugin->validate_form($frm, $err);
        // Save plugin config.
        if ($authplugin->process_config($frm)) {

            // Save field lock configuration.
            foreach ($frm as $name => $value) {
                if (preg_match('/^lockconfig_(.+?)$/', $name, $matches)) {
                    $plugin = "auth/$auth";
                    $name   = $matches[1];
                    set_config($name, $value, $plugin);
                }
            }
        }
		redirect($returnurl);
		exit;
    } else {
        foreach ($err as $key => $value) {
            $focus = "form.$key";
        }
    }
} else {
    $frm = (object)$_GET;
    $authplugin->validate_form($frm, $err);
    // $frmlegacystyle = get_config('auth/'.$auth);
    // $frmnewstyle    = get_config('auth_'.$auth);
     // array_merge((array)$frmlegacystyle, (array)$frmnewstyle);
}


$user_fields = $authplugin->userfields;
// $user_fields = array("firstname", "lastname", "email", "phone1", "phone2", "institution", "department", "address", "city",
// "country", "description", "idnumber", "lang");

// Get the auth title (from core or own auth lang files).
    $authtitle = $authplugin->get_title();
// Get the auth descriptions (from core or own auth lang files).
    $authdescription = $authplugin->get_description();

// Output configuration form.
echo $OUTPUT->header();

// Choose an authentication method.
echo "<form id=\"authmenu\" method=\"post\" action=\"azp.php\">\n";
echo "<div>\n";
echo "<input type=\"hidden\" name=\"sesskey\" value=\"".sesskey()."\" />\n";
echo "<input type=\"hidden\" name=\"auth\" value=\"".$auth."\" />\n";

// Auth plugin description.
echo $OUTPUT->box_start();
echo $OUTPUT->heading($authtitle);
echo $OUTPUT->box_start('informationbox');
echo $authdescription;
echo $OUTPUT->box_end();
echo "<hr />\n";
$authplugin->config_form($frm, $err, $user_fields);
echo $OUTPUT->box_end();
// echo '<p style="text-align: center"><input type="submit" value="' . get_string("savechanges") . "\" /></p>\n";
echo "</div>\n";
echo "</form>\n";

$PAGE->requires->string_for_js('unmaskpassword', 'core_form');
$PAGE->requires->yui_module('moodle-auth-passwordunmask', 'M.auth.passwordunmask');

echo $OUTPUT->footer();
exit;
