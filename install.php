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
 * register the OAuth2 services in the external services.
 *
 * @package   auth_swisseduid
 * @copyright 2017 Christian Glahn
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$wsManager = new webservice();
$service = $wsManager->get_external_service_by_shortname("TLA", IGNORE_MISSING);

if (!$service) {
    // only if the service is not already configured
    // insert the OAuth2 service to the external services.
    // This is required for PowerTLA and user control
    $servicedata = [
        "name" => "OAuth Services (via TLA Plugin)",
        "shortname" => "OAuth2",
        "enabled" => 1,
        "restrictedusers" => 0,
        "downloadfiles" => 1,
        "uploadfiles" => 1
    ];

    $servicedata["id"] = $wsManager->add_external_service($servicedata);
    $params = [
        'objectid' => $servicedata["id"]
    ];

    $event = \core\event\webservice_service_created::create($params);
    $event->trigger();
}
