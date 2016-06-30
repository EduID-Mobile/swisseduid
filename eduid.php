<?php
/* *********************************************************************** *
 * Edu-ID Caller
 *
 * This script will identify which service is requested and launches it.
 * *********************************************************************** */

// set the include path so we can find our classes
set_include_path("./lib" . PATH_SEPARATOR .
                get_include_path());

// include autoloader
include_once('eduid.auto.php');

use \EduID\Service\Error as ErrorService;

if(array_key_exists("PATH_INFO", $_SERVER)) {
    $pi = explode("/", $_SERVER["PATH_INFO"]);
    array_shift($pi);
    $serviceName = array_shift($pi);
}

if (!empty($serviceName)) {

    $serviceName = trim($serviceName);
    $ts = explode("-", $serviceName);
    $serviceName = "EduID\\";

    // create camel case classnames for dashed services
    $serviceName .= implode("", array_map(function($v) {return ucfirst(strtolower($v));}, $ts));

    if (class_exists($serviceName, true)) {
        $service = new $serviceName();
        $service->setDebugMode(true);

        // eventually load moodle
        // note that we use Ajax script to supress moodle's WS services being launched
        define('AJAX_SCRIPT', true);
        // load moodle's main configuration and setup
        require('../../config.php'); // lots of black magic is happening now

        // TODO check moodle's debug mode and reset service debugging
    }
    else {
        $service = new ErrorService(501 , "invalid service call to $serviceName");
    }
}

if (!isset($service)) {
    $service = new ErrorService(403 , "no service set");
}

// NOW run the service
$service->run();

?>
