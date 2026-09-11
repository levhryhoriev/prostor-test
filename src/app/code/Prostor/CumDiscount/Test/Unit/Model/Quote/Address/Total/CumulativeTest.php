<?php

declare(strict_types=1);

namespace Prostor\CumDiscount\Test\Unit\Model\Quote\Address\Total;

use DateTimeImmutable;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Validator\Currency as CurrencyValidator;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Api\Data\ShippingInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Item;
use Magento\Store\Model\Store;
use PHPUnit\Framework\TestCase;
use Prostor\CumDiscount\Api\LoyaltySnapshotProviderInterface;
use Prostor\CumDiscount\Model\Config\LoyaltyConfig;
use Prostor\CumDiscount\Model\Config\LoyaltyConfiguration;
use Prostor\CumDiscount\Model\Discount\DiscountCalculator;
use Prostor\CumDiscount\Model\Discount\Threshold;
use Prostor\CumDiscount\Model\Discount\ThresholdTable;
use Prostor\CumDiscount\Model\Loyalty\LoyaltySnapshot;
use Prostor\CumDiscount\Model\Quote\Address\Total\Cumulative;
use Prostor\CumDiscount\Model\Quote\PromoExcludedProductIds;
use Psr\Log\LoggerInterface;

class CumulativeTest extends TestCase
{
    public function testAuthenticatedMixedCartAppliesAndThenResetsItsOwnTotal(): void
    {
        $address = (new \ReflectionClass(Address::class))->newInstanceWithoutConstructor();
        $store = $this->createStub(Store::class);
        $store->method('getWebsiteId')->willReturn(7);
        $store->method('getId')->willReturn(4);
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getIsVirtual', 'getShippingAddress', 'getStore'])
            ->getMock();
        $quote->method('getIsVirtual')->willReturn(false);
        $quote->method('getShippingAddress')->willReturn($address);
        $quote->method('getStore')->willReturn($store);
        $quote->setData('customer_is_guest', false);
        $quote->setData('customer_id', 42);

        $config = $this->createStub(LoyaltyConfig::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('read')->willReturn($this->configuration());
        $currencyValidator = $this->createStub(CurrencyValidator::class);
        $currencyValidator->method('isValid')->with('UAH')->willReturn(true);
        $snapshot = new LoyaltySnapshot(
            $currencyValidator,
            42,
            8123.50,
            'UAH',
            new DateTimeImmutable('2026-01-01T00:00:00Z')
        );
        $provider = $this->createMock(LoyaltySnapshotProviderInterface::class);
        $provider->expects(self::exactly(2))->method('getSnapshot')->willReturn($snapshot, null);
        $priceCurrency = $this->createStub(PriceCurrencyInterface::class);
        $priceCurrency->method('round')->willReturnCallback(static fn (float $amount): float => round($amount, 2));
        $excluded = $this->createMock(PromoExcludedProductIds::class);
        $excluded->expects(self::once())->method('collect')->willReturn([2 => true]);
        $collector = new Cumulative(
            $config,
            $provider,
            new DiscountCalculator($priceCurrency),
            $this->createStub(LoggerInterface::class),
            $excluded
        );
        $assignment = $this->assignment([$this->item(100.0, 120.0, 1), $this->item(50.0, 75.0, 2)]);
        $total = (new \ReflectionClass(Total::class))->newInstanceWithoutConstructor();

        $collector->collect($quote, $assignment, $total);

        self::assertSame(-5.0, $address->getData(Cumulative::TOTAL_CODE . '_amount'));
        self::assertSame(-6.0, $address->getData('base_' . Cumulative::TOTAL_CODE . '_amount'));

        $collector->collect($quote, $assignment, $total);

        self::assertSame(0.0, $address->getData(Cumulative::TOTAL_CODE . '_amount'));
        self::assertSame(0.0, $address->getData('base_' . Cumulative::TOTAL_CODE . '_amount'));
    }

    private function configuration(): LoyaltyConfiguration
    {
        return new LoyaltyConfiguration(
            true,
            'https://loyalty.example.test',
            'token',
            3,
            300,
            'UAH',
            new ThresholdTable([new Threshold(4000.0, 3.0), new Threshold(8000.0, 5.0)])
        );
    }

    private function assignment(array $items): ShippingAssignmentInterface
    {
        $address = (new \ReflectionClass(Address::class))->newInstanceWithoutConstructor();
        $address->setData('address_type', Address::ADDRESS_TYPE_SHIPPING);
        $shipping = $this->createStub(ShippingInterface::class);
        $shipping->method('getAddress')->willReturn($address);
        $assignment = $this->createStub(ShippingAssignmentInterface::class);
        $assignment->method('getShipping')->willReturn($shipping);
        $assignment->method('getItems')->willReturn($items);

        return $assignment;
    }

    private function item(float $display, float $base, int $productId): Item
    {
        $item = (new \ReflectionClass(Item::class))->newInstanceWithoutConstructor();
        $item->setData('row_total', $display);
        $item->setData('base_row_total', $base);
        $item->setData('product_id', $productId);

        return $item;
    }
}
