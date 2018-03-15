<?php

namespace App\Services\Wikipedia;

use App\Services\APIHandler;
use GuzzleHttp\Client;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class WikipediaAPI
{
    protected $version;
    protected $clientId;
    protected $key;
    protected $logger;

    public function __construct() {
        $this->logger = new Logger('Wikipedia');
        $this->logger->pushHandler(new StreamHandler(storage_path().'/logs/wikipedia.log', Logger::DEBUG));
        $this->client = new Client([
            'base_uri' => 'https://en.wikipedia.org/api/rest_v1/',
            'handler' => APIHandler::create($this->logger)
        ]);
    }
}
