<?php

namespace App\Services\Google;

use App\Services\APIHandler;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;

class PhotosAPI extends GoogleAPI
{
    public static function fetch($photoId, &$options = [])
    {
        throw new Exception();
    }

    private function checkStatus($status)
    {
        switch ($status) {
            case 'OK':
                return true;
            case 'ZERO_RESULTS':
                $this->logger->debug('['.$status.'] Empty Resposne.');
                return false;
            case 'OVER_QUERY_LIMIT':
            case 'REQUEST_DENIED':
            case 'INVALID_REQUEST':
            case 'UNKNOWN_ERROR':
            default:
                $this->logger->error('['.$status.'] Invalid Resposne.');
                return false;
        }
    }
}