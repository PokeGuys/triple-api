<?php

namespace App\Services\Wikipedia;

use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class SearchAPI extends WikipediaAPI
{
    public function fetch($keyword) {
        $this->logger->debug('[Wikipedia] SearchAPI: Initial Request');
        return $this->parse($this->client->get("https://en.wikipedia.org/w/api.php", [
            'query' => [
                'action' => 'query',
                'list' => 'search',
                'srsearch' => $keyword,
                'format' => 'json',
                'srprop' => 'timestamp'
            ]
        ]));
    }

    private function parse($response) {
        $result = json_decode($response->getBody()->getContents());
        if (!empty($result->query->search)) {
            $this->logger->debug('[Wikipedia] SearchAPI: Request Succeed');
            return $result->query->search[0];
        } else {
            $this->logger->error('[Wikipedia] SearchAPI: Empty result.');
            return (object) ['error' => true, 'message' => 'Empty result'];
        }
    }
}
