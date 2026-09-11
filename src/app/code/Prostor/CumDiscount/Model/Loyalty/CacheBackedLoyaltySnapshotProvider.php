<?php

declare(strict_types=1);

namespace Prostor\CumDiscount\Model\Loyalty;

use Psr\Log\LoggerInterface;
use Prostor\CumDiscount\Model\Cache\Type;
use Prostor\CumDiscount\Model\Config\LoyaltyConfig;
use Prostor\CumDiscount\Model\Config\LoyaltyConfiguration;
use Prostor\CumDiscount\Api\LoyaltySnapshotProviderInterface;

class CacheBackedLoyaltySnapshotProvider implements LoyaltySnapshotProviderInterface
{
    public function __construct(
        private readonly LoyaltyConfig $config,
        private readonly LoyaltyHttpClient $client,
        private readonly Type $cache,
        private readonly LoggerInterface $logger,
        private readonly LoyaltySnapshotPayloadParser $snapshotPayloadParser
    ) {
    }

    public function getSnapshot(int $customerId, int $websiteId): ?LoyaltySnapshot
    {
        if ($customerId < 1 || $websiteId < 1) {
            $this->logger->warning('loyalty_configuration_failure');

            return null;
        }

        try {
            if (!$this->config->isEnabled($websiteId)) {
                return null;
            }

            $configuration = $this->config->read($websiteId);
        } catch (\Throwable $exception) {
            $this->logger->warning('loyalty_configuration_failure');

            return null;
        }

        $cacheKey = $this->cacheKey($customerId, $websiteId);

        try {
            $cachedSnapshot = $this->loadSnapshot($cacheKey, $customerId, $configuration);
        } catch (LoyaltyApiException $exception) {
            $this->logger->warning('loyalty_' . $exception->category() . '_failure');

            return null;
        } catch (\Throwable $exception) {
            $this->logger->warning('loyalty_cache_failure');

            return null;
        }

        if ($cachedSnapshot instanceof LoyaltySnapshot) {
            return $cachedSnapshot;
        }

        try {
            $snapshot = $this->client->fetch($configuration, $customerId);
        } catch (LoyaltyApiException $exception) {
            $this->logger->warning('loyalty_' . $exception->category() . '_failure');

            return null;
        } catch (\Throwable $exception) {
            $this->logger->warning('loyalty_transport_failure');

            return null;
        }

        try {
            $saved = $this->cache->save(
                json_encode($snapshot->toCachePayload(), JSON_THROW_ON_ERROR),
                $cacheKey,
                [],
                $configuration->cacheTtl()
            );
        } catch (\Throwable $exception) {
            $this->logger->warning('loyalty_cache_failure');

            return null;
        }

        if (!$saved) {
            $this->logger->warning('loyalty_cache_failure');

            return null;
        }

        return $snapshot;
    }

    private function loadSnapshot(
        string $cacheKey,
        int $customerId,
        LoyaltyConfiguration $configuration
    ): ?LoyaltySnapshot {
        $payload = $this->cache->load($cacheKey);

        if ($payload === false) {
            return null;
        }

        if (!is_string($payload)) {
            throw new LoyaltyApiException('validation');
        }

        try {
            $snapshot = $this->snapshotPayloadParser->parseCachedPayload($payload);
        } catch (\Throwable $exception) {
            throw new LoyaltyApiException('validation');
        }

        if ($snapshot->customerId() !== $customerId || $snapshot->currency() !== $configuration->currency()) {
            throw new LoyaltyApiException('validation');
        }

        return $snapshot;
    }

    private function cacheKey(int $customerId, int $websiteId): string
    {
        return 'prostor_cumdiscount_loyalty_v1_' . hash('sha256', $websiteId . ':' . $customerId);
    }
}
