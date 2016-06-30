<?php
/* *********************************************************************** *
 *
 * *********************************************************************** */

namespace EduID\Validator;

class Base extends \RESTling\Validator {
    protected $db;

    protected $id_type;     // service, client, user
    protected $uuid;        // respective uuid

    protected $allowEmptyMethod = array();
    protected $requireEmptyMethod = array();


    /**
     * allowEmpty() and requireEmtpy() are almost like RESTling\Validator::ignoreOperation
     * except that for data validators these functions will test if the
     * service data is set (or not). It will avoid validation if the minimal data
     * requirements are not met, otherwise the validation will proceed as usual.
     */
    public function allowEmpty($methodList) {
        if (!empty($methodList)) {
            if (!is_array($methodList)) {
                $methodLis = array($methodList);
            }
            foreach ($methodList as $m) {
                $m = strtolower($m);
                $this->allowEmptyMethod[] = $m;
            }
        }
    }

    public function requireEmpty($methodList) {
        if (!empty($methodList)) {
            if (!is_array($methodList)) {
                $methodList = array($methodList);
            }
            foreach ($methodList as $m) {
                $m = strtolower($m);
                $this->requireEmptyMethod[] = $m;
            }
        }
    }

    protected function checkDataForMethod() {
        if (!empty($this->data)) {
            if (in_array($this->method, $this->requireEmptyMethod)) {
                $this->log("No Data Must Be Sent For " . $this->method);
                return false;
            }
        }
        else if (!in_array($this->method, $this->requireEmptyMethod)) {
            $this->log("Data missing for  " . $this->method);
            return false;
        }

        return true;
    }

    protected function checkAuthToken() {
        // verify that there is a client token
        $gT = $this->service->getAuthToken();
        if (empty($gT)) {
            $this->log("no token found");
            $this->service->forbidden();
            return false;
        }
        return true;
    }

    /**
     * Check if data fields as present and not empty
     *
     * @protected function checkDataFields($fieldList)
     *
     * @param array $fieldList - list of expected non empty fields
     *
     * @returns bool - false if any presented field is missing or empty.
     */
    protected function checkDataFields($fieldList) {
        if(!empty($fieldList)) {

            foreach ($fieldList as $k) {
                if (!array_key_exists($k, $this->data) ||
                    empty($this->data[$k])) {
                    $this->log("missing value in key " . $k);
                    return false;
                }
            }
        }

        return true;
    }

}

?>
