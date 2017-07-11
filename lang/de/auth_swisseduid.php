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
 * @package   auth_swisseduid
 * @copyright 2017 Christian Glahn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['auth_swisseduiddescription'] = 'Swiss edu-ID authentication plugin';
$string['pluginname'] = 'Swiss edu-ID authentication plugin';
$string['eduid_user_info_endpoint'] = 'Swiss edu-ID user information Endpoint';
$string['service_token_duration'] = 'Service token Dauer in Stunden';
$string['app_token_duration'] = 'App token Dauer in Stunden';
$string['generate_new_key'] = 'Generieren neue key';
$string['generate_private_key'] = 'Private Key generieren';
$string['private_key'] = 'Private Key';
$string['authority_display_name'] = 'Authority\'s Display Name';
$string['authority_name'] = 'Name der Authority';
$string['update_authority'] = 'Aktualisieren Authority';
$string['add_key'] = 'Hinzufügen Key';
$string['cancel'] = 'Stornieren';
$string['change_mapping'] = 'Änderungsmapping';
$string['oauth2_redirect_uri_is'] = 'Ihre OAuth2 redirect_uri ist';
$string['public_key_is'] = 'Ihr Public Key ist:';
$string['registered_auth_services'] = 'Registrierte Authorization Services';
$string['registered_keys'] = 'Registrierte Keys';
$string['private_key_present'] = 'Private Key ist vorhanden';
$string['oauth2_authority_url'] = 'Authority·OAuth2·URL';
$string['oauth2_authority_base_url'] = 'Authority·OAuth2 base·URL';
$string['oauth2_client_id'] = 'client_id (wie von der Authority zur Verfügung gestellt)';
$string['oauth2_kid'] = 'Key Id (wie von der Authority zur Verfügung gestellt)';
$string['oauth2_jku'] = 'jku (wie von der Authority zur Verfügung gestellt)';
$string['oauth2_issuer_id'] = 'Issuer Id (wie von der Authority zur Verfügung gestellt)';
$string['oauth2_issuer_value'] = 'Issuer value';
$string['oauth2_moodle_auth_type_optional'] = 'Moodle auth Typ (optional)';
$string['oauth2_moodle_auth_type'] = 'Moodle auth Typ';
$string['oauth2_save_key'] = 'Key aktualisieren';
$string['oauth2_code'] = 'Code';
$string['oauth2_implicit'] = 'Implicit';
$string['oauth2_hybrid'] = 'Hybrid';
$string['oauth2_assertion'] = 'Assertion';
$string['oauth2_flow_type'] = 'OAuth2/OpenID Connect Flow Type';
$string['oauth2_key_id'] = 'Issuer Key Id (wie von der Authority zur Verfügung gestellt)';
$string['oauth2_jwk_source_url'] = 'JWK Source URL (wie von der Authority zur Verfügung gestellt)';
$string['oauth2_crypt_key'] = 'Key (wie von der Authority zur Verfügung gestellt)';
// Authority table.
$string['eduid_add_new_authority'] = 'Neue Authority hinzufügen';
$string['eduid_drop_authority_entry'] = 'Authority löschen';
$string['eduid_authorities'] = 'Authority Name';
$string['eduid_authority_name'] = 'Authority Name';
$string['eduid_authority_url'] = 'Authority URL';
$string['eduid_authority_shared_token'] = 'Authority shared token';
$string['eduid_privkey_for_authority'] = 'Private key';
$string['eduid_authority_public_key'] = 'Public key';
$string['eduid_invalid_form'] = 'Bitte füllen Sie alle Pflichtfelder aus.';
