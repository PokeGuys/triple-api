<?php

namespace App\Services\Foursquare;

use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class DetailAPI extends FoursquareAPI
{
    public function fetch($placeId) {
        $this->logger->debug('[Foursquare] DetailAPI: Initial Request');
        try {
            return $this->parse($this->client->get("venues/$placeId", [
                'query' => $this->appendConfig()
            ]));
        } catch (Exception $e) {
            return (object) ['error' => true, 'message' => 'Unexpected error'];
        }
    }

    private function parse($response) {
        $place = json_decode($response->getBody()->getContents());
        if ($place->meta->code == 200) {
            $this->logger->debug('[Foursquare] DetailAPI: Request Succeed');
            return $place->response->venue;
        } else {
            $this->logger->error('[Foursquare] DetailAPI: Invalid Resposne.');
            return (object) ['error' => true, 'message' => $place->errorMessage ?? 'Unexpected error'];
        }
    }
}
