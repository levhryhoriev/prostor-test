<?php

declare(strict_types=1);

namespace Prostor\CumDiscount\Model\Quote\Address\Total;

use Magento\Framework\Phrase;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use Magento\Quote\Model\Quote\Item;
use Prostor\CumDiscount\Api\LoyaltySnapshotProviderInterface;
use Prostor\CumDiscount\Model\Config\LoyaltyConfig;
use Prostor\CumDiscount\Model\Discount\DiscountCalculator;
use Prostor\CumDiscount\Model\Quote\PromoExcludedProductIds;
use Psr\Log\LoggerInterface;

class Cumulative extends AbstractTotal
{
    public const TOTAL_CODE = 'prostor_cumdiscount';

    public function __construct(
        private readonly LoyaltyConfig $config,
        private readonly LoyaltySnapshotProviderInterface $snapshotProvider,
        private readonly DiscountCalculator $discountCalculator,
        private readonly LoggerInterface $logger,
        private readonly PromoExcludedProductIds $promoExcludedProductIds
    ) {
        $this->setCode(self::TOTAL_CODE);
    }

    public function _resetState(): void
    {
        parent::_resetState();
        $this->setCode(self::TOTAL_CODE);
    }

    public function collect(Quote $quote, ShippingAssignmentInterface $shippingAssignment, Total $total): self
    {
        parent::collect($quote, $shippingAssignment, $total);

        $persistedAddress = $quote->getIsVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();
        $persistedAddress->setData(self::TOTAL_CODE . '_amount', 0.0);
        $persistedAddress->setData('base_' . self::TOTAL_CODE . '_amount', 0.0);

        $address = $shippingAssignment->getShipping()->getAddress();
        if (!$this->isApplicableAddress($quote, $address)) {
            return $this;
        }

        $customerId = $this->customerId($quote);
        if ($customerId === null) {
            return $this;
        }

        $store = $quote->getStore();
        $websiteId = (int) $store->getWebsiteId();
        try {
            if ($websiteId < 1 || !$this->config->isEnabled($websiteId)) {
                return $this;
            }

            $configuration = $this->config->read($websiteId);
        } catch (\Throwable $exception) {
            $this->logger->warning('loyalty_configuration_failure');

            return $this;
        }

        $snapshot = $this->snapshotProvider->getSnapshot($customerId, $websiteId);
        if ($snapshot === null) {
            return $this;
        }

        $percentage = $configuration->thresholdTable()->percentageFor($snapshot->spentAmount());
        if ($percentage === null) {
            return $this;
        }

        $quoteItems = $shippingAssignment->getItems();
        $amounts = $this->eligibleTotals(
            $quoteItems,
            $this->promoExcludedProductIds->collect($quoteItems, (int) $store->getId())
        );

        $displayDiscount = $this->discountCalculator->calculate($amounts['display'], $percentage);
        $baseDiscount = $this->discountCalculator->calculate($amounts['base'], $percentage);
        if ($displayDiscount === 0.0 && $baseDiscount === 0.0) {
            return $this;
        }

        $persistedAddress->setData(self::TOTAL_CODE . '_amount', -$displayDiscount);
        $persistedAddress->setData('base_' . self::TOTAL_CODE . '_amount', -$baseDiscount);
        $this->_setAmount(-$displayDiscount);
        $this->_setBaseAmount(-$baseDiscount);

        return $this;
    }

    public function fetch(Quote $quote, Total $total): array
    {
        $address = $quote->getIsVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();
        $amount = (float) ($address->getData(self::TOTAL_CODE . '_amount') ?? 0.0);
        if ($amount === 0.0) {
            return [];
        }

        return ['code' => self::TOTAL_CODE, 'title' => $this->getLabel(), 'value' => $amount];
    }

    public function getLabel(): Phrase
    {
        return __('Prostor Cumulative');
    }

    private function isApplicableAddress(Quote $quote, Address $address): bool
    {
        return $address->getAddressType() === Address::ADDRESS_TYPE_SHIPPING
            || ($quote->getIsVirtual() && $address->getAddressType() === Address::ADDRESS_TYPE_BILLING);
    }

    private function customerId(Quote $quote): ?int
    {
        if ($quote->getCustomerIsGuest()) {
            return null;
        }

        $customerId = filter_var($quote->getCustomerId(), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $customerId === false ? null : $customerId;
    }

    private function eligibleTotals(array $items, array $excludedProductIds): array
    {
        $display = 0.0;
        $base = 0.0;

        foreach ($items as $item) {
            if (!$item instanceof Item
                || $item->getParentItem() !== null
                || $this->isPromoExcluded($item, $excludedProductIds)
            ) {
                continue;
            }

            $displayRowTotal = $this->amount($item->getRowTotal());
            $baseRowTotal = $this->amount($item->getBaseRowTotal());
            if ($displayRowTotal === null || $baseRowTotal === null) {
                continue;
            }

            $display += $displayRowTotal;
            $base += $baseRowTotal;
        }

        return ['display' => $display, 'base' => $base];
    }

    private function isPromoExcluded(Item $item, array $excludedProductIds): bool
    {
        return isset($excludedProductIds[(int) $item->getProductId()]);
    }

    private function amount(mixed $value): ?float
    {
        if (!is_numeric($value) || (float) $value < 0.0) {
            return null;
        }

        return (float) $value;
    }
}
