<?php

declare(strict_types=1);

namespace Prostor\CumDiscount\Test\Unit\Model\Discount;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Prostor\CumDiscount\Model\Discount\Threshold;
use Prostor\CumDiscount\Model\Discount\ThresholdTable;

class ThresholdTableTest extends TestCase
{
    public static function cases(): array
    {
        return [[3500.0, null], [4000.0, 3.0], [7999.99, 3.0], [8000.0, 5.0], [8123.50, 5.0]];
    }

    #[DataProvider('cases')]
    public function testItSelectsTheHighestApplicableThreshold(float $spentAmount, ?float $percentage): void
    {
        $table = new ThresholdTable([new Threshold(8000.0, 5.0), new Threshold(4000.0, 3.0)]);

        self::assertSame($percentage, $table->percentageFor($spentAmount));
    }
}
