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

function has_key($data, $key) {
    $data = obj2array($data);
    return (is_string($key) &&
            strlen($key) &&
            array_key_exists($key, $data) &&
            !empty($data[$key]));
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
	// this exception is always thrown by the OauthManager->storeKey the because $errMessage is passed and it is not empty string
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
