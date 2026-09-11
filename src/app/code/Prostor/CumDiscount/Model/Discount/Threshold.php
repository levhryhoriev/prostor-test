<?php

declare(strict_types=1);

namespace Prostor\CumDiscount\Model\Discount;

class Threshold
{
    public function __construct(
        private readonly float $minimumSpend,
        private readonly float $percentage
    ) {
        if ($this->minimumSpend < 0.0 || $this->percentage < 0.0 || $this->percentage > 100.0) {
            throw new \InvalidArgumentException('The threshold is invalid.');
        }
    }

    public function minimumSpend(): float
    {
        return $this->minimumSpend;
    }

    public function percentage(): float
    {
        return $this->percentage;
    }
}
