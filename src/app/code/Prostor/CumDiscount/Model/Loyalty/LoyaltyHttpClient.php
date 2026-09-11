<?php

declare(strict_types=1);

namespace Prostor\CumDiscount\Model\Loyalty;

use Magento\Framework\HTTP\ClientFactory;
use Prostor\CumDiscount\Model\Config\LoyaltyConfiguration;

class LoyaltyHttpClient
{
    public function __construct(
        private readonly ClientFactory $clientFactory,
        private readonly LoyaltySnapshotPayloadParser $snapshotPayloadParser
    ) {
    }

    public function fetch(LoyaltyConfiguration $configuration, int $customerId): LoyaltySnapshot
    {
        if ($customerId < 1) {
            throw new LoyaltyApiException('validation');
        }

        try {
            $client = $this->clientFactory->create();
            $client->setTimeout($configuration->timeout());
            $client->addHeader('Authorization', 'Bearer ' . $configuration->token());
            $client->get($this->requestUrl($configuration->baseUrl(), $customerId));
            $status = $client->getStatus();
            $body = $client->getBody();
        } catch (\Throwable $exception) {
            throw new LoyaltyApiException('transport');
        }

        if ($status !== 200) {
            throw new LoyaltyApiException('response');
        }

        try {
            $snapshot = $this->snapshotPayloadParser->parseApiResponse($body);
            if ($snapshot->customerId() !== $customerId || $snapshot->currency() !== $configuration->currency()) {
                throw new \InvalidArgumentException('The loyalty payload is invalid.');
            }

            return $snapshot;
        } catch (\Throwable $exception) {
            throw new LoyaltyApiException('validation');
        }
    }

    private function requestUrl(string $baseUrl, int $customerId): string
    {
        return $baseUrl . '/loyalty/cumulative?' . http_build_query(
            ['customer_id' => (string) $customerId, 'window_days' => '90'],
            '',
            '&',
            PHP_QUERY_RFC3986
        );
    }
}
