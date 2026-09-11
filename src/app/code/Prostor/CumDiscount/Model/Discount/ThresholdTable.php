<?php

declare(strict_types=1);

namespace Prostor\CumDiscount\Model\Discount;

class ThresholdTable
{
    private array $thresholds;

    public function __construct(array $thresholds)
    {
        $this->thresholds = $thresholds;

        foreach ($this->thresholds as $threshold) {
            if (!$threshold instanceof Threshold) {
                throw new InvalidThresholdConfiguration('Thresholds must contain threshold values.');
            }
        }

        usort(
            $this->thresholds,
            static fn (Threshold $left, Threshold $right): int => $left->minimumSpend() <=> $right->minimumSpend()
        );

        $this->assertUniqueMinimumSpends();
    }

    public function percentageFor(float $spentAmount): ?float
    {
        if ($spentAmount < 0.0) {
            throw new \InvalidArgumentException('The spend amount is invalid.');
        }

        $percentage = null;

        foreach ($this->thresholds as $threshold) {
            if ($spentAmount < $threshold->minimumSpend()) {
                break;
            }

            $percentage = $threshold->percentage();
        }

        return $percentage;
    }

    private function assertUniqueMinimumSpends(): void
    {
        $previousMinimumSpend = null;

        foreach ($this->thresholds as $threshold) {
            if ($previousMinimumSpend !== null && $previousMinimumSpend === $threshold->minimumSpend()) {
                throw new InvalidThresholdConfiguration('Threshold minimum spends must be unique.');
            }

            $previousMinimumSpend = $threshold->minimumSpend();
        }
    }
}
