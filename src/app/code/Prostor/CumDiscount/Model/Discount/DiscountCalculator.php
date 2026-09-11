<?php

declare(strict_types=1);

namespace Prostor\CumDiscount\Model\Discount;

use Magento\Framework\Pricing\PriceCurrencyInterface;

class DiscountCalculator
{
    public function __construct(private readonly PriceCurrencyInterface $priceCurrency)
    {
    }

    public function calculate(float $eligibleSubtotal, float $percentage): float
    {
        if ($eligibleSubtotal <= 0.0 || $percentage <= 0.0) {
            return 0.0;
        }

        if ($percentage > 100.0) {
            throw new \InvalidArgumentException('The discount percentage is invalid.');
        }

        return (float) $this->priceCurrency->round($eligibleSubtotal * $percentage / 100.0);
    }
}
