<?php

/**
 * reduces the provided array, so it contains only the provided keys.
 *
 * If invalid parameters are provided, the function returns an
 * empty array.
 *
 * The result is an array containing only the provided keys. If a key is
 * missing or empty in the data, then it won't be present in the result set.
 *
 * @param array|object $data - where the keys are picked from
 * @param string|array $keys - which keys to pick.
 * @return array
 */
function pick_keys($data, $keys) {
    $keys = str2array($keys);
    $data = obj2array($data);

    if (empty($data) || empty($keys)) {
        return [];
    }
    return array_filter($data, function($v, $k) use ($keys) {
        return (!empty($v) && in_array($k, $keys));
    }, ARRAY_FILTER_USE_BOTH);
}

function verify_keys($data, $keys, $errMessage="") {
    $keys = str2array($keys);
    $data = obj2array($data);

    if (empty($data) && empty($keys))
        return true;

    if (empty($data))
        return false;

    $res = pick_keys($data, $keys);
    $retval = (count($res) == count($keys));
    if (strlen($errMessage)) {
        throw new Exception($errMessage);
    }
    return $retval;
}

function ensure_array($data) {
    if (is_array($data))
        return $data;
    return [];
}

function obj2array($data) {
    if (is_object($data))
        $data = (array) $data;
    return ensure_array($data);
}

function str2Array($data) {
    if (is_string($data) && strlen($data))
        $data = [$data];
    return ensure_array($data);
}

?>
