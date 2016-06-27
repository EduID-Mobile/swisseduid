<?php

namespace EduID\Model;

class ModelFoundation extends \RESTling\Logger {

    public static function isAssoc($a) {
        return ($a != array_values($a));
    }

    public static function reduceBools($c, $e) {
        if (!isset($c)) $c = true;
        return ($c && $e);
    }

    protected function randomString($length=10) {
        $resstring = "";
        $chars = "abcdefghijklmnopqrstuvwxyz._ABCDEFGHIJKLNOPQRSTUVWXYZ-1234567890";
        $len = strlen($chars);
        for ($i = 0; $i < $length; $i++)
        {
            $x = rand(0, $len-1);
            $resstring .= substr($chars, $x, 1);
        }
        return $resstring;
    }

    protected function generateUuid() {
        return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0x0fff ) | 0x4000,
            mt_rand( 0, 0x3fff ) | 0x8000,
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
        );
    }

    protected function checkMandatoryFields($object, $fields) {

        $a = array();

        if (self::isAssoc($fields)) {
            foreach ($fields as $e => $v) {
                if (array_key_exists($e, $object) &&
                        !empty($object[$e])) {
                    if (empty($v)) {
                        // behave like non assoc version
                        $a[] = true;
                    }
                    else if (is_array($v) && in_array($object[$e], $v)) {
                        $a[] = true;
                    }
                    else if (!is_array($v) && $object[$e] == $v) {
                        $a[] = true;
                    }
                    else {
                        $a[] = false;
                    }
                }
                else {
                    $a[] = false;
                }
            }
        }
        else {
            $a = array_map(function ($e) use ($object) {
                return (array_key_exists($e, $object) &&
                        !empty($object[$e]));
            }, $fields);
        }
        return array_reduce($a, "\EduID\ModelFoundation::reduceBools");
    }

    protected function filterValidObjects($objList, $fields) {
        $f = function ($e) use ($fields) {
            return $this->checkMandatoryFields($e, $fields);
        };

        return array_filter($objList, $f);
    }

    protected function filterValidDataObjects($objList, $fields) {
        $f = function ($e) use ($fields) {
            return $this->checkMandatoryFields($e, $fields);
        };

        return array_filter($objList, $f);
    }

    // ensures that all attributes are actually present
    protected function ensureAttributes($objList, $attrMap) {
        if (!empty($objList) &&
            !empty($attrMap)) {

            $om = function ($obj) use ($attrMap) {
                if (is_array($obj)) {
                    $am = function ($attr) use (&$obj, $attrMap) {
                        if (!array_key_exists($attr, $obj)) {
                            $obj[$attr] = '';
                        }
                    };
                    array_map($am, $attrMap);
                    return $obj;
                }
            };
            return array_map($om, $objList);
        }
        return array();
    }

    // almost like array_values, but using ordered attributes
    protected function flattenAttributes($objList, $attrMap) {
        if (!empty($objList) &&
            !empty($attrMap)) {

            $om = function ($obj) use ($attrMap) {
                $am = function ($attr) use ($obj, $attrMap) {
                    return array_key_exists($attr, $obj)? $obj[$attr] : "";
                };
                return array_map($am, $attrMap);
            };
            return array_map($om, $objList);
        }
        return array();
    }
    
    protected function mapToAttribute($objList, $attributeName, $quote=false) {
        $f = function ($e) use ($attributeName, $quote) {
            return $quote ? $this->db->quote($e[$attributeName]) : $e[$attributeName];
        };

        return array_map($f, $objList);
    }
}
?>
