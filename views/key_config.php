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
 * @package   auth_oauth2
 * @copyright 2017 Christian Glahn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();
?>

<input type="hidden" id="azp" name="azp" value="<?php echo $azpInfo->id; ?>" />
<input type="hidden" id="keyid" name="keyid" value="<?php echo $keyInfo->id; ?>" />
<div id="settings">
    <div>
        <ul>
            <li><input id="kid" name="kid" type="text" placeholder="<?php echo get_string('oauth2_kid', 'auth_oauth2');?>"
                value="<?php echo $keyInfo->kid; ?>"></li>
            <li><input id="jku" name="jku" type="text" placeholder="<?php echo get_string('oauth2_jku', 'auth_oauth2');?>"
                value="<?php echo $keyInfo->jku;?>"></li>
            <li><textarea id="crypt_key" name="crypt_key"><?php echo $keyInfo->crypt_key;?></textarea></li>
        </ul>
        <div>
            <input type="submit" id="storekey" name="storekey" value="<?php echo get_string('oauth2_update_key', 'auth_oauth2');?>">
        </div>
    </div>
</div>