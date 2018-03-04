<?php

namespace App\Services\Foursquare;

use App\Services\APIHandler;
use GuzzleHttp\Client;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class FoursquareAPI
{
    protected $version;
    protected $clientId;
    protected $key;
    protected $logger;

    public function __construct() {
        $this->logger = new Logger('Foursquare');
        $this->logger->pushHandler(new StreamHandler(storage_path().'/logs/foursquare.log', Logger::DEBUG));
        $this->clientId = env('FOURSQUARE_CLIENT_ID');
        $this->key = env('FOURSQUARE_PRIVATE_KEY');
        $this->version = env('FOURSQUARE_VERSION');
        $this->client = new Client([
            'base_uri' => 'https://api.foursquare.com/v2/',
            'handler' => APIHandler::create($this->logger)
        ]);
    }

    public function appendConfig($options = []) {
        return array_merge($options, [
            'v' => $this->version,
            'client_secret' => $this->key,
            'client_id' => $this->clientId
        ]);
    }
}