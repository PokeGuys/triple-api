<?php

namespace App\Http\Traits;

use GuzzleHttp\Client;
use Guzzle\Http\Exception;

trait TripHelper {
    protected $API_KEY;
    
    public function __construct() {
        $API_KEY = env('GOOGLE_PLACES_API_KEY', null);
    }

    public function getEndpoint($location) {

    }

    public function sendEndpoint($uri, $data = '') {
        $client = new Client();
        try {
            if (is_array($data) && !isset($data['form_params'])) {
                $tmp = $data;
                unset($data);
                $data['form_params'] = $tmp;
            }
            $result = $client->request($data == '' ? 'GET' : 'POST', $uri, $data);
            return $result->getBody();
        } catch (ClientException $e) {
            return -1;
        } catch (ServerErrorResponseException $e) {
            return -1;
        }
    }

    public function isJson($string) {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }
}