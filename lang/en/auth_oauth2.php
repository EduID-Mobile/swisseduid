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

$string['auth_oauth2description'] = 'The standard authentication plugin for the Swiss universities';
$string['pluginname'] = 'Swiss edu-ID authentication plugin';
$string['eduid_user_info_endpoint'] = 'Swiss edu-ID user information endpoint';
$string['service_token_duration'] = 'Service token duration in hours';
$string['app_token_duration'] = 'App token duration in hours';
$string['generate_new_key'] = 'Generate new key';
$string['generate_private_key'] = 'Generate Private Key';
$string['cancel'] = 'Cancel';
$string['authority_display_name'] = 'Authority\'s Display Name';
$string['authority_name'] = 'Authority name';
$string['update_authority'] = 'Update Authority';
$string['add_key'] = 'Add Key';
$string['change_mapping'] = 'Change Mapping';
$string['cancel'] = 'Cancel';
$string['oauth2_redirect_uri_is'] = 'Your OAuth2 redirect_uri is';
$string['public_key_is'] = 'Your Public Key is:';
$string['registered_auth_services'] = 'Registered Authorization Services';
$string['registered_keys'] = 'Registered Keys';
$string['private_key_present'] = 'Private Key is present';
$string['oauth2_authority_url'] = 'Authority OAuth2 URL';
$string['oauth2_authority_base_url'] = 'Authority OAuth2 base URL';
$string['oauth2_client_id'] = 'client_id (as provided by the authority)';
$string['oauth2_kid'] = 'Key Id (as provided by the Authority)';
$string['oauth2_jku'] = 'jku (as provided by the Authority)';
$string['oauth2_issuer_id'] = 'Issuer Id (as provided by the Authority)';
$string['oauth2_issuer_value'] = 'Issuer value';
$string['oauth2_moodle_auth_type_optional'] = 'Moodle auth type (optional)';
$string['oauth2_moodle_auth_type'] = 'moodle auth type';
$string['oauth2_save_key'] = 'Update Key';
$string['oauth2_code'] = 'Code';
$string['oauth2_implicit'] = 'Implicit';
$string['oauth2_hybrid'] = 'Hybrid';
$string['oauth2_assertion'] = 'Assertion';
$string['oauth2_flow_type'] = 'OAuth2/OpenID Connect Flow Type';
$string['oauth2_key_id'] = 'Issuer Key Id (as provided by the Authority)';
$string['oauth2_jwk_source_url'] = 'JWK Source URL (as provided by the Authority)';
$string['oauth2_crypt_key'] = 'Key (as provided by the Authority)';
// Authority table.
$string['eduid_add_new_authority'] = 'Add New Authority';
$string['eduid_drop_authority_entry'] = 'Drop Authority';
$string['eduid_authorities'] = 'Authority name';
$string['eduid_authority_name'] = 'Authority name';
$string['eduid_authority_url'] = 'Authority url';
$string['eduid_authority_shared_token'] = 'Authority shared token';
$string['eduid_privkey_for_authority'] = 'Private key';
$string['eduid_authority_public_key'] = 'Public key';
$string['eduid_invalid_form'] = 'Please complete all the required fields.';

