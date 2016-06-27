<?php

spl_autoload_register(function ($class) {
    $class = ltrim($class, '\\');

    // error_log("eduid-auto: " . $class);

    $parts = explode('\\', $class);

    $root = array_shift($parts);

    if (!empty($root) && !empty($parts)) {
        // error_log("eduid-auto: OK");

        $cpath = array();
        // direct namespace
        $cpath[] = $root . "/" . implode("/", $parts) . ".class.php";

        // sub-directory namespaces
        $cpath[] = $root . "/classes/" . implode("/", $parts) . ".class.php";
        $cpath[] = $root . "/src/" . implode("/", $parts) . ".class.php";
        $cpath[] = $root . "/lib/" . implode("/", $parts) . ".class.php";

        // for developer prefixed namespaces
        $root = array_shift($parts);
        $cpath[] = strtolower($root) . "/src/" . implode("/", $parts) . ".php";
        $cpath[] = strtolower($root) . "/lib/" . implode("/", $parts) . ".php";

        $prefixes = explode(PATH_SEPARATOR, get_include_path());

        foreach ( $prefixes as $p ) {
            foreach ($cpath as $path) {
                // error_log("eduid-auto: $p/$path");
                if (file_exists($p . "/" . $path)) {
                    require_once $p . "/" . $path;
                    // error_log("eduid-auto: loaded");
                    break 2;
                }
            }
        }
    }
});

?>
