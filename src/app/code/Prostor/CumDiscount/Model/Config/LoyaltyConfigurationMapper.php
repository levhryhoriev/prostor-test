<?php

declare(strict_types=1);

namespace Prostor\CumDiscount\Model\Config;

use Prostor\CumDiscount\Model\Discount\ThresholdConfigurationMapper;

class LoyaltyConfigurationMapper
{
    public function __construct(
        private readonly LoyaltyConfigurationFactory $configurationFactory,
        private readonly ThresholdConfigurationMapper $thresholdMapper
    ) {
    }

    public function map(
        bool $enabled,
        string $baseUrl,
        string $token,
        int $timeout,
        int $cacheTtl,
        string $currency,
        string $serializedThresholds
    ): LoyaltyConfiguration {
        return $this->configurationFactory->create(
            [
                'enabled' => $enabled,
                'baseUrl' => $baseUrl,
                'token' => $token,
                'timeout' => $timeout,
                'cacheTtl' => $cacheTtl,
                'currency' => $currency,
                'thresholdTable' => $this->thresholdMapper->map($serializedThresholds),
            ]
        );
    }
}
