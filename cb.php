<?php

// init moodle
// require_once "../../config.php";
require_once "/var/www/html/moodle/config.php";
// load our function
require_once __DIR__ . "/lib/OAuthCallback.php";

// we have no work on our own
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
    // normal use when the user comes via the login page
    // triggers the authorization request
    $callback->handleAuthorization();
}
elseif (array_key_exists("state", $_GET)) {
    // response from the authorization endpoint
    $callback->authorizeUser();
}
elseif (array_key_exists("error", $_GET)) {
    http_response_code(403);
    exit;
}
else {
    // this one handles the assertion (an any future extension)
    // by passing ALL parameters to the AP token endpoint
    $callback->authorizeAssertion();
}

// ensure that moodle is not kicking in
exit;
?>
