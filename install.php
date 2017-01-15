<?php

/**
 * register the OAuth2 services in the external services.
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
