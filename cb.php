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

require_once(__DIR__ . '/vendor/autoload.php');

// Init moodle.
require(__DIR__.'/../../config.php');
// require_once('/var/www/html/moodle/config.php'); // development only, due to symlinks

// Load our function.
require_once(__DIR__ . '/lib/OAuthCallback.php');

// This is an AUTHENTICATION initiator/callback. At this point moodle does
// does not know about the user.
// COMMENTED ON PURPOSE require_login(); // NEVER UNCOMMENT

// We have no work on our own.
$callback = new OAuthCallback();

if (!$callback->isActive()) {
    http_response_code(503);
    exit;
}

if (empty($_GET)) {
    http_response_code(403);
    exit;
}

if (array_key_exists("id", $_GET)) {
    // Normal use when the user comes via the login page.
    // Triggers the authorization request.
    $callback->handleAuthorization();
} else if (array_key_exists("state", $_GET)) {
    // Response from the authorization endpoint.
    $callback->authorizeUser();
} else if (array_key_exists("error", $_GET)) {
    http_response_code(403);
    exit;
}
elseif (array_key_exists("assertion", $_GET)) {
    // This one handles the assertion (an any future extension).
    // By passing ALL parameters to the AP token endpoint.
    $callback->authorizeAssertion();
}
else {
    http_response_code(403); // bad request
    exit;
}

// Ensure that moodle is not kicking in.
exit;
