<?php

namespace App\Http\Traits;

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Guzzle\Http\Exception;
use GuzzleHttp\HandlerStack;
use function GuzzleHttp\Promise\each_limit;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Illuminate\Support\Facades\Cache;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;
use Kevinrob\GuzzleCache\Storage\LaravelCacheStorage;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

trait FourSquareHelper
{
    private $version;
    private $clientId;
    private $mapKey;
    private $placeKey;
    private $logger;
    private $stack;
    private $client;

    public function __construct()
    {
        $this->logger = new Logger('FourSquare');
        $this->logger->pushHandler(new StreamHandler(storage_path().'/logs/foursquare.log', Logger::DEBUG));
        $this->mapKey = env('GOOGLE_MAP_PRIVATE_KEY');
        $this->clientId = env('FOURSQUARE_CLIENT_ID');
        $this->placeKey = env('FOURSQUARE_PRIVATE_KEY');
        $this->version = env('FOURSQUARE_VERSION');
        $this->stack = HandlerStack::create();
        $this->stack->push(
            new CacheMiddleware(
                new PrivateCacheStrategy(
                    new LaravelCacheStorage(
                        Cache::store('file')
                    )
                )
            ),
            'cache'
        );
        $this->client = new Client(['base_uri' => 'https://api.foursquare.com/v2/', 'handler' => $this->stack]);
    }

    public function fetchRecommandedPlaces($categoryId, $location, $options = [])
    {
        $query = array_merge($this->appendConfig($options), [
            'll' => join(',', $location),
            'categoryId' => is_string($categoryId) ? $categoryId : join(',', $categoryId),
        ]);
        $places = $this->send('GET', 'search/recommendations', [
            'query' => $query
        ]);
        if (!isset($places->error)) {
            if ($places->meta->code == 200) {
                $this->logger->debug('fetchRecommandedPlaces Request Finished', $query);
                if ($places->response->group->totalResults == 0) {
                    $this->logger->debug('fetchRecommandedPlaces Empty Response', $query);
                    return [];
                }
                return $places->response->group->results;
            } else {
                $this->logger->error('['.$status.'] Invalid Resposne.');
                return (object) ['error' => true, 'message' => $places->errorMessage ?? 'Unexpected error'];
            }
        } else {
            return $places;
        }
    }

    public function fetchPlaces($categoryId, $location, $options = [])
    {
        $query = array_merge($this->appendConfig($options), [
            'll' => join(',', $location),
            'categoryId' => is_string($categoryId) ? $categoryId : join(',', $categoryId),
        ]);
        $places = $this->send('GET', 'venues/explore', [
            'query' => $query
        ]);
        if (!isset($places->error)) {
            if ($places->meta->code == 200) {
                $this->logger->debug('fetchPlaces Request Finished', $query);
                return $places->response;
            } else {
                $this->logger->error('['.$status.'] Invalid Resposne.');
                return (object) ['error' => true, 'message' => $places->errorMessage ?? 'Unexpected error'];
            }
        } else {
            return $places;
        }
    }

    public function fetchHours($placeId)
    {
        $hours = $this->send('GET', "venues/$placeId/hours", [
            'query' => $this->appendConfig()
        ]);
        if (!isset($hours->error)) {
            if ($hours->meta->code == 200) {
                $this->logger->debug('fetchHours Request Finished', [$placeId]);
                return $hours->response;
            } else {
                $this->logger->error('['.$status.'] Invalid Resposne.');
                return (object) ['error' => true, 'message' => $hours->errorMessage ?? 'Unexpected error'];
            }
        } else {
            return $hours;
        }
    }

    public function fetchPhotos($placeId)
    {
        $photos = $this->send('GET', "venues/$placeId/photos", [
            'query' => $this->appendConfig()
        ]);
        if (!isset($photos->error)) {
            if ($photos->meta->code == 200) {
                $this->logger->debug('fetchPhotos Request Finished', [$placeId]);
                return $photos->response;
            } else {
                $this->logger->error('['.$status.'] Invalid Resposne.');
                return (object) ['error' => true, 'message' => $photos->errorMessage ?? 'Unexpected error'];
            }
        } else {
            return $photos;
        }
    }


    private function appendConfig($options = [])
    {
        return array_merge($options, [
            'v' => $this->version,
            'client_secret' => $this->placeKey,
            'client_id' => $this->clientId
        ]);
    }

    private function send($method = 'GET', $uri, $data = [])
    {
        try {
            $response = $this->client->request($method, $uri, $data);
            return json_decode($response->getBody());
        } catch (ConnectException $e) {
            $request = $e->getRequest();
            $response = $e->getResponse();
            $this->logger->error('['.$response->getStatusCode().'] Connection failed');
            $this->logger->debug('Request Detail:', $request);
            $this->logger->debug('Response Detail:', $response);
            return (object) ['error' => true, 'message' => 'Connection failure'];
        } catch (ClientException $e) {
            $request = $e->getRequest();
            $response = $e->getResponse();
            $this->logger->error('['.$response->getStatusCode().'] Invalid API Parameters');
            $this->logger->debug('Request Detail:', $request);
            $this->logger->debug('Response Detail:', $response);
            return (object) ['error' => true, 'message' => 'Invalid API Parameters'];
        } catch (ServerException $e) {
            $request = $e->getRequest();
            $response = $e->getResponse();
            $this->logger->error('['.$response->getStatusCode().'] Connection failed');
            $this->logger->debug('Request Detail:', $request);
            $this->logger->debug('Response Detail:', $response);
            return (object) ['error' => true, 'message' => 'Google Place API failure'];
        }
    }
}
