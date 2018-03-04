<?php

namespace App\Services\Foursquare;

use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class OpeningHourAPI extends FoursquareAPI
{
    public function fetch($placeId) {
        $this->logger->debug('[Foursquare] OpeningHourAPI: Initial Request.', [$placeId]);
        return $this->client->getAsync("venues/$placeId/hours", [
            'query' => $this->appendConfig()
        ]);
    }

    public function parse($response) {
        if (isset($response['state']) && $response['state'] == 'rejected') {
            $this->logger->debug('[Foursquare] OpeningHourAPI: Too Many Requests');
            throw new ServiceUnavailableHttpException(120, 'Unexpected Error');
        }
        $hours = json_decode($response['value']->getBody()->getContents());
        if ($hours->meta->code == 200) {
            $this->logger->debug('[Foursquare] OpeningHourAPI: Request Succeed.');
            return $hours->response;
        } else {
            $this->logger->error('[Foursquare] OpeningHourAPI: Invalid Resposne.');
            return (object) ['error' => true, 'message' => $hours->errorMessage ?? 'Unexpected error'];
        }
    }
}