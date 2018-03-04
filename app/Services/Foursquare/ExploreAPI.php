<?php

namespace App\Services\Foursquare;

use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class ExploreAPI extends FoursquareAPI
{
    public function fetch($radius, $location, $options = []) {
        $options = array_merge($options, [
            'll' => join(',', $location),
            'radius' => $radius,
            'section' => 'sights',
            'venuePhotos' => 1
        ]);
        $this->logger->debug('[Foursquare] ExploreAPI: Initial Request', $options);
        return $this->client->getAsync('venues/explore', [
            'query' => $this->appendConfig($options)
        ]);
    }

    public function parse($response) {
        if (isset($response['state']) && $response['state'] == 'rejected') {
            $this->logger->debug('[Foursquare] ExploreAPI: Too Many Requests');
            throw new ServiceUnavailableHttpException(120, 'Unexpected Error');
        }
        $places = json_decode($response['value']->getBody()->getContents());
        if ($places->meta->code == 200) {
            $this->logger->debug('[Foursquare] ExploreAPI: Request Succeed', (array) $places->response->suggestedBounds->ne);
            if ($places->response->totalResults == 0) {
                $this->logger->debug('[Foursquare] ExploreAPI: Empty Response', (array) $places->response->suggestedBounds->ne);
                return [];
            }
            return $places->response->groups[0]->items;
        } else {
            $this->logger->error('[Foursquare] ExploreAPI: Invalid Resposne.', (array) $places->response->suggestedBounds->ne);
            return (object) ['error' => true, 'message' => $places->errorMessage ?? 'Unexpected error'];
        }
    }
}