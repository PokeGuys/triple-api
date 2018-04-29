<?php

namespace App\Services\Google;

class DirectionAPI extends GoogleAPI
{
    private $altmode;

    public function __construct($altmode) {
        parent::__construct();
        $this->altmode = $altmode;
    }

    public function fetch($origin, $destination, &$options = [])
    {
        $options = array_merge($options, [
            'origin' => join(',', $origin),
            'destination' => join(',', $destination),
            'key' => $this->key,
        ]);
        $response = $this->client->request('GET', 'directions/json', [
            'query' => $options
        ]);
        $direction = json_decode($response->getBody()->getContents());
        if (!isset($direction->error)) {
            if ($this->checkStatus($direction->status)) {
                $this->logger->debug('[Google] DirectionAPI: Succeed', $options);
                $route = $direction->routes[0];
                if (!empty($route->warnings)) {
                    if (isset($this->altmode)) {
                        $options['mode'] = $this->altmode;
                        $route->mode = $options['mode'];
                        return $this->fetch($origin, $destination, $options);
                    }
                    $options['mode'] = 'walking';
                }
                $route->mode = $options['mode'];
                return $route;
            } else {
                $this->logger->error('[Google] DirectionAPI: Failed', $options);
                $this->logger->error('[Google] DirectionAPI: Response', [$direction]);
                if (isset($direction->available_travel_modes)) {
                    if (in_array('WALKING', $direction->available_travel_modes)) {
                        $options['mode'] = 'walking';
                        return $this->fetch($origin, $destination, $options);
                    } else if (in_array('DRIVING', $direction->available_travel_modes)) {
                        $options['mode'] = 'driving';
                        return $this->fetch($origin, $destination, $options);
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