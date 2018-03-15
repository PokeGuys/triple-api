<?php

namespace App\Services\Wikipeidia;

use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class SummaryAPI extends FoursquareAPI
{
    public function fetch($title) {
        $this->logger->debug('[Wikipedia] SummaryAPI: Initial Request', $options);
        return $this->client->getAsync("page/summary/$title");
    }

    public function parse($response) {
        if (isset($response['state']) && $response['state'] == 'rejected') {
            $this->logger->debug('[Wikipedia] SummaryAPI: Unexpected Error');
            throw new ServiceUnavailableHttpException(120, 'Unexpected Error');
        }
        $summary = json_decode($response['value']->getBody()->getContents());
        if (isset($summary->extract)) {
            $this->logger->debug('[Wikipedia] SummaryAPI: Request Succeed');
            return $summary->extract;
        } else {
            $this->logger->error('[Wikipedia] SummaryAPI: Invalid Resposne.');
            return (object) ['error' => true, 'message' => $logger->detail ?? 'Unexpected error'];
        }
    }
}