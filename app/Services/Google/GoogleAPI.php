<?php

namespace App\Services\Google;

use App\Services\APIHandler;
use GuzzleHttp\Client;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class GoogleAPI
{
    protected $key;
    protected $logger;

    public function __construct() {
        $this->logger = new Logger('GoogleAPI');
        $this->logger->pushHandler(new StreamHandler(storage_path().'/logs/maps.log', Logger::DEBUG));
        $this->key = env('GOOGLE_MAP_PRIVATE_KEY');
        $this->client = new Client([
            'base_uri' => 'https://maps.googleapis.com/maps/api/',
            'handler' => APIHandler::create($this->logger)
        ]);
    }

    public function appendConfig($options = []) {
        return array_merge($options, [
            'key' => $this->key
        ]);
    }
}