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
<div id="settings">
    <p>
            <?php echo get_string('oauth2_redirect_uri_is', 'auth_oauth2'); ?> <pre><?php echo "$tlaurl/cb"; ?></pre>
    </p>
        <p><label for="name"><?php echo get_string('authority_display_name',
                     'auth_oauth2'); ?></label><input id="name" name="name" type="text" placeholder="<?php echo
                     get_string('authority_name', 'auth_oauth2'); ?>" value="<?php echo $azpInfo->name; ?>"></p>
        <p><label for="iss"><?php echo get_string('oauth2_client_id',
                    'auth_oauth2'); ?></label><input id="issuer" name="issuer" type="text" placeholder="<?php echo
                     get_string('oauth2_issuer_value', 'auth_oauth2'); ?>
            " value="<?php echo $azpInfo->issuer; ?>"></p>
        <p><label for="client_id"><?php echo get_string('oauth2_client_id',
                    'auth_oauth2'); ?></label><input id="client_id" name="client_id" type="text" placeholder="<?php echo
                     get_string('oauth2_client_id', 'auth_oauth2'); ?>" value="<?php echo $azpInfo->client_id; ?>"></p>
        <p><label for="url"><?php echo get_string('oauth2_authority_base_url',
                    'auth_oauth2'); ?></label><input id="url" name="url" type="text" placeholder="<?php echo
                    get_string('oauth2_authority_url', 'auth_oauth2'); ?>" value="<?php echo $azpInfo->url; ?>"></p>
        <p><label for="auth_type"><?php echo get_string('oauth2_moodle_auth_type_optional',
                    'auth_oauth2'); ?></label><input id="auth_type" name="auth_type" type="text" placeholder="<?php echo get_string('oauth2_moodle_auth_type',
                    'auth_oauth2'); ?>" value="<?php echo $azpInfo->auth_type?>"></p>
        <p> <label for="flow">OAuth2/OpenID Connect Flow Type</label>
            <select id="flow" name="flow">
                <option value="code" <?php if ($azpInfo->flow === "code") echo 'selected="selected"';?><?php echo
                    get_string('oauth2_code', 'auth_oauth2'); ?></option>
                <option value="implict" <?php if ($azpInfo->flow === "implicit") echo 'selected="selected"';?><?php echo
                    get_string('oauth2_implicit', 'auth_oauth2'); ?></option>
                <option value="hybrid" <?php if ($azpInfo->flow === "hybrid") echo 'selected="selected"';?><?php echo
                    get_string('oauth2_hybrid', 'auth_oauth2'); ?></option>
                <option value="assertion" <?php if ($azpInfo->flow === "assertion") echo 'selected="selected"';?><?php echo
                    get_string('oauth2_assertion', 'auth_oauth2'); ?></option>
            </select>
        </p>
</div>
<div>
    <input type="submit" id="storeazp" name="storeazp" value="Update Authority">
</div>
    <!-- list of keys -->
<div id="keyList">
    <h2>Registered Keys</h2>
    <ul>
        <?php
foreach ($keyList as $key) {
    if (empty($key->kid) && empty($key->jku)) {
        continue;
    }

    if (!empty($key->token_id)) {
        // skip secondary keys
        continue;
    }
    echo '<li><a href="'.$azpurl . $azpInfo->id.'&keyid=' . $key->id. '">';
    if (!empty($key->kid)) {
        echo $key->kid;
    }
    elseif (!empty($key->jku)) {
        echo $key->jku;
    }
    echo '</a></li>';
}
            ?>
    </ul>
    <div>
        <input type="hidden" id="keyid" name="keyid" value=""/>

            <p><label for="kid">Key Id (as provided by Authority)</label><input id="kid" name="kid" type="text" placeholder="Key Id (as provided by Authority)"></p>
            <p><label for="jku">JWK Source URL (as provided by the Authority)</label><input id="jku" name="jku" type="text" placeholder="jku (as provided by the authority)"></p>
            <p><label for="crypt_key">Key (as provided by Authority)</label><textarea id="crypt_key" name="crypt_key"></textarea></p>
        <div>
            <input type="submit" id="storekey" name="storekey" value="Add Key">
        </div>
    </div>
</div>
