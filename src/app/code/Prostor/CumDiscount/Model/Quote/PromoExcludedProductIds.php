<?php

declare(strict_types=1);

namespace Prostor\CumDiscount\Model\Quote;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Quote\Model\Quote\Item;

class PromoExcludedProductIds
{
    public function __construct(
        private readonly CollectionFactory $productCollectionFactory
    ) {
    }

    public function collect(array $items, int $storeId): array
    {
        $productIds = $this->productIds($items);
        if ($productIds === []) {
            return [];
        }

        $products = $this->productCollectionFactory->create();
        $products->setStoreId($storeId);
        $products->addAttributeToSelect('promo_excluded');
        $products->addIdFilter($productIds);

        $excludedProductIds = [];
        foreach ($products->getItems() as $product) {
            if ((string) $product->getData('promo_excluded') === '1') {
                $excludedProductIds[(int) $product->getId()] = true;
            }
        }

        return $excludedProductIds;
    }

    private function productIds(array $items): array
    {
        $productIds = [];

        foreach ($items as $item) {
            if (!$item instanceof Item || $item->getParentItem() !== null) {
                continue;
            }

            $productId = filter_var($item->getProductId(), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($productId !== false) {
                $productIds[$productId] = $productId;
            }
        }

        return array_values($productIds);
    }
}
