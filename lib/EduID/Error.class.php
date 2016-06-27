<?php
/* *********************************************************************** *
 * Error Service
 *
 * The Error Service is a dummy service to be called whenever an invalid
 * service is requested by a client.
 *
 * The Error Service will not record misbehaving clients, but it may do so
 * in a later version.
 * *********************************************************************** */

namespace EduID;

class Error extends \RESTling\Logger {
    private $code = 400;

    public function __construct($code, $msg) {
        $this->code = $code;
        $this->mark("Start Error Service");
        $this->log("ErrorService::__construct - FATAL ERROR: Error Service launched");

        $this->log("internal message ($code): " . $msg);
    }

    public function run() {
        if (function_exists('http_response_code'))
        {
            http_response_code($this->code);
        }
        else {
            switch($code) {
                case 401: $text = 'Unauthorized'; break;
                case 402: $text = 'Payment Required'; break;
                case 403: $text = 'Forbidden'; break;
                case 404: $text = 'Not Found'; break;
                case 405: $text = 'Method Not Allowed'; break;
                case 406: $text = 'Not Acceptable'; break;
                case 407: $text = 'Proxy Authentication Required'; break;
                case 408: $text = 'Request Time-out'; break;
                case 409: $text = 'Conflict'; break;
                case 410: $text = 'Gone'; break;
                case 411: $text = 'Length Required'; break;
                case 412: $text = 'Precondition Failed'; break;
                case 413: $text = 'Request Entity Too Large'; break;
                case 414: $text = 'Request-URI Too Long'; break;
                case 415: $text = 'Unsupported Media Type'; break;
                case 429: $text = 'Too Many Requests'; break; // RFC 6585 defined response code for twitter's 420 code
                case 501: $text = 'Not Implemented'; break;
                case 502: $text = 'Bad Gateway'; break;
                case 503: $text = 'Service Unavailable'; break;
                case 504: $text = 'Gateway Time-out'; break;
                case 505: $text = 'HTTP Version not supported'; break;
                case 400:
                default:
                    $code = 400;
                    $text = 'Bad Request';
                    break;

            }
            header('HTTP/1.1 $code $text');
        }
        echo $msg;
        $this->mark("END Error Service");
    }
}

?>