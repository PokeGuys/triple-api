<?php

namespace App\Services\Foursquare;

use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class DetailAPI extends FoursquareAPI
{
    public function fetch($placeId) {
        $this->logger->debug('[Foursquare] DetailAPI: Initial Request');
        return $this->client->getAsync("venues/$placeId", [
            'query' => $this->appendConfig($options)
        ]);
    }

    public function parse($response) {
        if (isset($response['state']) && $response['state'] == 'rejected') {
            $this->logger->debug('[Foursquare] DetailAPI: Too Many Requests');
            throw new ServiceUnavailableHttpException(120, 'Unexpected Error');
        }
        $place = json_decode($response['value']->getBody()->getContents());
        if ($place->meta->code == 200) {
            $this->logger->debug('[Foursquare] DetailAPI: Request Succeed');
            return $place->response->venue;
        } else {
            $this->logger->error('[Foursquare] DetailAPI: Invalid Resposne.');
            return (object) ['error' => true, 'message' => $place->errorMessage ?? 'Unexpected error'];
        }
    }
}