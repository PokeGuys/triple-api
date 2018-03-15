<?php

namespace App\Services\Wikipeidia;

use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class SearchAPI extends FoursquareAPI
{
    public function fetch($keyword) {
        $this->logger->debug('[Wikipedia] SearchAPI: Initial Request');
        return $this->client->getAsync("https://en.wikipedia.org/w/api.php", [
            'query' => [
                'action' => 'query',
                'list' => 'search',
                'srsearch' => $keyword,
                'srwhat' => 'nearmatch',
                'format' => 'json',
                'srprop' => 'timestamp'
            ]
        ]);
    }

    public function parse($response) {
        if (isset($response['state']) && $response['state'] == 'rejected') {
            $this->logger->debug('[Wikipedia] SearchAPI: Unexpected Error');
            throw new ServiceUnavailableHttpException(120, 'Unexpected Error');
        }
        $result = json_decode($response['value']->getBody()->getContents());
        if (!empty($result->query->search)) {
            $this->logger->debug('[Wikipedia] SearchAPI: Request Succeed');
            return $result->query->search[0];
        } else {
            $this->logger->error('[Wikipedia] SearchAPI: Empty result.');
            return (object) ['error' => true, 'message' => 'Empty result'];
        }
    }
}