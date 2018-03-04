<?php

namespace App\Services\Foursquare;

use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class RecommandationAPI extends FoursquareAPI
{
    public function fetch($categoryId, $location, $options = []) {
        $options = array_merge($options, [
            'll' => join(',', $location),
            'categoryId' => $categoryId
        ]);
        $this->logger->debug('[Foursquare] RecommandedAPI: Initial Request', $options);
        return $this->client->getAsync('search/recommendations', [
            'query' => $this->appendConfig($options)
        ]);
    }

    public function parse($response) {
        if (isset($response['state']) && $response['state'] == 'rejected') {
            $this->logger->debug('[Foursquare] RecommandedAPI: Too Many Requests');
            throw new ServiceUnavailableHttpException(120, 'Unexpected Error');
        }
        $places = json_decode($response['value']->getBody()->getContents());
        if ($places->meta->code == 200) {
            $this->logger->debug('[Foursquare] RecommandedAPI: Request Succeed', (array) $places->response->context->geoParams);
            if ($places->response->group->totalResults == 0) {
                $this->logger->debug('[Foursquare] RecommandedAPI: Empty Response', (array) $places->response->context->geoParams);
                return [];
            }
            return $places->response->group->results;
        } else {
            $this->logger->error('[Foursquare] RecommandedAPI: Invalid Resposne.', (array) $places->response->context->geoParams);
            return (object) ['error' => true, 'message' => $places->errorMessage ?? 'Unexpected error'];
        }
    }
}