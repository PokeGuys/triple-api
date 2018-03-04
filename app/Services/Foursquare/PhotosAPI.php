<?php

namespace App\Services\Foursquare;

use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class PhotosAPI extends FoursquareAPI
{
    public function fetch($placeId) {
        return $this->client->getAsync("venues/$placeId/photos", [
            'query' => $this->appendConfig()
        ]);
    }

    public function parse($response) {
        if (isset($response['state']) && $response['state'] == 'rejected') {
            $this->logger->debug('[Foursquare] PhotosAPI: Too Many Requests');
            throw new ServiceUnavailableHttpException(120, 'Unexpected Error');
        }
        $photos = json_decode($response['value']->getBody()->getContents());
        if ($photos->meta->code == 200) {
            return $photos->response;
        } else {
            $this->logger->error('[Foursquare] PhotosAPI: Invalid Resposne.');
            return (object) ['error' => true, 'message' => $photos->errorMessage ?? 'Unexpected error'];
        }
    }
}