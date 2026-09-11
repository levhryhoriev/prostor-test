<?php

declare(strict_types=1);

namespace Prostor\CumDiscount\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Boolean;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute as CatalogAttribute;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddPromoExcludedProductAttribute implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->startSetup();

        try {
            $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
            $attributeId = $eavSetup->getAttributeId(Product::ENTITY, 'promo_excluded');

            if ($attributeId) {
                $attribute = $eavSetup->getAttribute(Product::ENTITY, 'promo_excluded');
                $this->assertCompatibleAttribute($attribute);
            } else {
                $eavSetup->addAttribute(
                    Product::ENTITY,
                    'promo_excluded',
                    [
                        'type' => 'int',
                        'label' => 'Exclude from Prostor Cumulative Discount',
                        'input' => 'boolean',
                        'source' => Boolean::class,
                        'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                        'visible' => true,
                        'required' => false,
                        'user_defined' => true,
                        'default' => '0',
                        'group' => 'General',
                        'used_in_product_listing' => true,
                        'is_used_in_grid' => false,
                        'is_visible_in_grid' => false,
                        'is_filterable_in_grid' => false,
                        'attribute_model' => CatalogAttribute::class,
                    ]
                );
            }
        } finally {
            $this->moduleDataSetup->endSetup();
        }

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }

    private function assertCompatibleAttribute(mixed $attribute): void
    {
        if (!is_array($attribute)) {
            throw new \RuntimeException('Existing promo_excluded attribute metadata cannot be read.');
        }

        $expectedMetadata = [
            'backend_type' => 'int',
            'frontend_input' => 'boolean',
            'is_global' => (string) ScopedAttributeInterface::SCOPE_GLOBAL,
            'source_model' => Boolean::class,
        ];

        foreach ($expectedMetadata as $field => $expectedValue) {
            $actualValue = $attribute[$field] ?? null;

            if ($field === 'source_model' && is_string($actualValue)) {
                $actualValue = ltrim($actualValue, '\\');
            }

            if ((string) $actualValue !== $expectedValue) {
                throw new \RuntimeException(
                    sprintf(
                        'Existing promo_excluded attribute metadata is incompatible: %s must be %s.',
                        $field,
                        $expectedValue
                    )
                );
            }
        }
    }
}
