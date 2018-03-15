<?php

namespace App\Services\Wikipedia;

use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class SummaryAPI extends WikipediaAPI
{
    public function fetch($title) {
        $this->logger->debug('[Wikipedia] SummaryAPI: Initial Request');
        return $this->parse($this->client->get("page/summary/$title"));
    }

    public function parse($response) {
        $summary = json_decode($response->getBody()->getContents());
        if (isset($summary->extract)) {
            if (isset($summary->type) && $summary->type == 'disambiguation') {
                $this->logger->error('[Wikipedia] SummaryAPI: Disambiguation Resposne.');
                return (object) ['error' => true, 'message' => 'Unexpected error'];
            }
            $this->logger->debug('[Wikipedia] SummaryAPI: Request Succeed');
            return $summary->extract;
        } else {
            $this->logger->error('[Wikipedia] SummaryAPI: Invalid Resposne.');
            return (object) ['error' => true, 'message' => 'Unexpected error'];
        }
    }
}
