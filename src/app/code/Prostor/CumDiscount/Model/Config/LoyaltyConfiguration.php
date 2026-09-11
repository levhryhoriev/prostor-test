<?php

declare(strict_types=1);

namespace Prostor\CumDiscount\Model\Config;

use Prostor\CumDiscount\Model\Discount\ThresholdTable;

class LoyaltyConfiguration
{
    public function __construct(
        private readonly bool $enabled,
        private readonly string $baseUrl,
        private readonly string $token,
        private readonly int $timeout,
        private readonly int $cacheTtl,
        private readonly string $currency,
        private readonly ThresholdTable $thresholdTable
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    public function token(): string
    {
        return $this->token;
    }

    public function timeout(): int
    {
        return $this->timeout;
    }

    public function cacheTtl(): int
    {
        return $this->cacheTtl;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function thresholdTable(): ThresholdTable
    {
        return $this->thresholdTable;
    }
}
