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

global $OUTPUT;
?>
<div id="settings">
    <!-- global private key settings -->
    <div id="private_key">
		<?php if (isset($PK)) { ?>
			<div><?php echo get_string('private_key_present', 'auth_oauth2') ?></div>
		<?php } ?>
		<div>
			<textarea id="pk" name="pk"></textarea>
		</div>
		<div>
			<input type="submit" id="gen_key" name="gen_key" value="<?php echo get_string('generate_private_key', 'auth_oauth2'); ?>"/>
		</div>
    </div>
    <!-- list of authorities -->
    <div id="authorityList">
    	<?php echo $OUTPUT->heading(get_string('registered_auth_services', 'auth_oauth2'), 3);?>
        <ul>
            <?php foreach ($authorities as $azp) {
				echo '<li><a href="'.$azpurl . $azp->id.'">'.$azp->name.'</a></li>';
			} ?>
        </ul>
        <div>
            <p>
                <?php echo get_string('oauth2_redirect_uri_is', 'auth_oauth2'); ?>: <pre><?php echo "$tlaurl/cb"?></pre>
            </p>
            <p>
                <?php echo get_string('public_key_is', 'auth_oauth2'); ?>
            </p>
            <p>
				<pre>
					<?php echo $pubKey; ?>
				</pre>
			</p>
            <p>
				<label for="name"><?php echo get_string('authority_name', 'auth_oauth2'); ?>:</label>
				<input id="name" name="name" type="text">
			</p>
            <p>
				<label for="client_id"><?php echo get_string('oauth2_client_id', 'auth_oauth2'); ?>:</label>
				<input id="client_id" name="client_id" type="text">
			</p>
            <p>
				<label for="url"><?php echo get_string('oauth2_authority_url', 'auth_oauth2'); ?>:</label>
				<input id="url" name="url" type="text">
			</p>
        </div>
        <input type="hidden" id="flow" name="flow" value="code"/>
    </div>
</div>
