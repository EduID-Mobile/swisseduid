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
 * ***********************************************************************
 * Edu-ID Caller
 *
 * This script will identify which service is requested and launches it.
 * ***********************************************************************
 *
 * @package   auth_oauth2
 * @copyright 2017 Christian Glahn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Set the include path so we can find our classes.
set_include_path("./lib" . PATH_SEPARATOR . get_include_path());

// Include autoloader.
require_once('eduid.auto.php');

use \EduID\Error as ErrorService;

if (array_key_exists("PATH_INFO", $_SERVER)) {
    $pi = explode("/", $_SERVER["PATH_INFO"]);
    array_shift($pi);
    $serviceName = array_shift($pi);
}

if (!empty($serviceName)) {

    $serviceName = trim($serviceName);
    $ts = explode("-", $serviceName);
    $serviceName = "EduID\\";

    // Create camel case classnames for dashed services.
    $serviceName .= implode("", array_map(function($v) {return ucfirst(strtolower($v));}, $ts));

    if (class_exists($serviceName, true)) {
        $service = new $serviceName();
        $service->setDebugMode(true);

        // Eventually load moodle.
        // Note that we use Ajax script to supress moodle's WS services being launched.
        define('AJAX_SCRIPT', true);
        // Load moodle's main configuration and setup.
        require('../../config.php'); // Lots of black magic is happening now.

        // TODO check moodle's debug mode and reset service debugging.
    } else {
        $service = new ErrorService(501 , "invalid service call to $serviceName");
    }
}

if (!isset($service)) {
    $service = new ErrorService(403 , "no service set");
}

// NOW run the service.
$service->run();