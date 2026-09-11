<?php

declare(strict_types=1);

namespace Prostor\CumDiscount\Test\Unit\Model\Discount;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use PHPUnit\Framework\TestCase;
use Prostor\CumDiscount\Model\Discount\DiscountCalculator;

class DiscountCalculatorTest extends TestCase
{
    public function testItUsesMagentoPriceRoundingAtTheAmountBoundary(): void
    {
        $priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $priceCurrency->expects(self::once())->method('round')->with(1.005)->willReturn(1.01);

        $discount = (new DiscountCalculator($priceCurrency))->calculate(20.10, 5.0);

        self::assertSame(1.01, $discount);
    }
}
