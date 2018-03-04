<?php

namespace App\Http\Traits;

use GuzzleHttp\Client;
use Guzzle\Http\Exception;
use GuzzleHttp\HandlerStack;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Illuminate\Support\Facades\Cache;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;
use Kevinrob\GuzzleCache\Storage\LaravelCacheStorage;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

trait GoogleMapHelper
{
    private $mapkey;
    private $placekey;
    private $logger;
    private $stack;

    public function __construct()
    {
        $this->logger = new Logger('GoogleAPI');
        $this->logger->pushHandler(new StreamHandler(storage_path().'/logs/place.log', Logger::DEBUG));
        $this->mapkey = env('GOOGLE_MAP_PRIVATE_KEY');
        $this->placekey = env('GOOGLE_PLACE_PRIVATE_KEY');
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
    }

    public function fetchDistance($origin, $destination, $options = [])
    {
        $options = array_merge($options, [
            'origin' => 'place_id:'.$origin,
            'destination' => 'place_id:'.$destination,
            'key' => $this->mapkey,
        ]);
        $direction = $this->send('GET', 'directions/json', [
            'query' => $options
        ]);
        if (!isset($direction->error)) {
            if ($this->checkStatus($direction->status)) {
                $route = $direction->routes[0];
                if (isset($route->warnings)) {
                    $options['mode'] = 'walking';
                }
                $route->mode = $options['mode'];
                return $route;
            } else {
                $this->logger->error('Request Details: ', $options);
                $this->logger->error('Response Details: ', [$direction]);
                if (isset($direction->available_travel_modes)) {
                    if (in_array('WALKING', $direction->available_travel_modes)) {
                        $options['mode'] = 'walking';
                        return $this->fetchDistance($origin, $destination, $options);
                    } else if (in_array('DRIVING', $direction->available_travel_modes)) {
                        $options['mode'] = 'driving';
                        return $this->fetchDistance($origin, $destination, $options);
                    } else {
                        return (object) ['error' => true, 'message' => 'Zero result'];
                    }
                } else {
                    return (object) ['error' => true, 'message' => $direction->error_message];
                }
            }
        } else {
            return $direction;
        }
    }

    public function fetchPlaces($location, $radius, array $options = [])
    {
        $geolocation = implode(',', $location);
        $options = array_merge($options, [
            'key' => $this->placekey,
            'location' => $geolocation,
            'radius' => $radius
        ]);
        $places = $this->send('GET', 'place/nearbysearch/json', [
            'query' => $options
        ]);
        if (!isset($places->error)) {
            if ($this->checkStatus($places->status)) {
                return $places->results;
            } else {
                $this->logger->error('Request Deatils: ', $options);
                $this->logger->error('Resposne Deatils: ', [$places]);
                return (object) ['error' => true, 'message' => $places->error_message ?? 'Zero result'];
            }
        } else {
            return $places;
        }
    }

    public function fetchPlaceInfo($id, array $options = [])
    {
        $options = array_merge($options, [
            'key' => $this->placekey,
            'placeid' => $id,
        ]);
        $info = $this->send('GET', 'place/details/json', [
            'query' => $options
        ]);
        if (!isset($info->error)) {
            if ($this->checkStatus($info->status)) {
                return $info->result;
            } else {
                $this->logger->error('Request Deatils: ', $options);
                $this->logger->error('Resposne Deatils: ', [$info]);
                return (object) ['error' => true, 'message' => $info->error_message ?? 'Zero result'];
            }
        } else {
            return $info;
        }
    }

    public function fetchPlacePhotos($id, $width = 400)
    {
        $options = [
            'key' => $this->placekey,
            'photoreference' => $id,
            'maxwidth' => $width
        ];
        $photo = $this->send('GET', 'place/photo', [
            'query' => $options,
            'allow_redirects' => FALSE
        ]);
        return $photo;
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

    private function send($method = 'GET', $uri, $data = [])
    {
        $client = new Client(['base_uri' => 'https://maps.googleapis.com/maps/api/', 'handler' => $this->stack]);
        try {
            $response = $client->request($method, $uri, $data);
            if ($response->getStatusCode() == 301) {
                return $response->getHeader('Location')[0];
            }
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
