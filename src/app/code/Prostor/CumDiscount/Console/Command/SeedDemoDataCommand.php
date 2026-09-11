<?php

declare(strict_types=1);

namespace Prostor\CumDiscount\Console\Command;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\Cache\Manager as CacheManager;
use Magento\Framework\App\State;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\InventoryCatalogApi\Api\DefaultSourceProviderInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SeedDemoDataCommand extends Command
{
    private const CUSTOMER_EMAIL = 'reviewer@example.test';

    private const CUSTOMER_PASSWORD = 'Review123!';

    private const PRODUCT_QUANTITY = 100.0;

    private const CONFIG_PREFIX = 'prostor_cumdiscount/general/';

    public function __construct(
        private readonly AccountManagementInterface $accountManagement,
        private readonly CustomerInterfaceFactory $customerFactory,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly ProductFactory $productFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly DefaultSourceProviderInterface $defaultSourceProvider,
        private readonly SourceItemInterfaceFactory $sourceItemFactory,
        private readonly SourceItemsSaveInterface $sourceItemsSave,
        private readonly StoreManagerInterface $storeManager,
        private readonly WriterInterface $configWriter,
        private readonly EncryptorInterface $encryptor,
        private readonly CacheManager $cacheManager,
        private readonly IndexerRegistry $indexerRegistry,
        private readonly State $appState
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('prostor:cumdiscount:seed-demo')
            ->setDescription('Creates local reviewer data for Prostor Cumulative Discount');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->appState->setAreaCode(Area::AREA_GLOBAL);

        $website = $this->storeManager->getWebsite();
        $websiteId = (int) $website->getId();

        $this->saveConfiguration($websiteId);
        $this->createCustomer($websiteId);
        $this->saveProduct(
            $websiteId,
            'prostor-demo-eligible',
            'Prostor Demo Eligible Product',
            '1000.00',
            false
        );
        $this->saveProduct(
            $websiteId,
            'prostor-demo-excluded',
            'Prostor Demo Promo Excluded Product',
            '500.00',
            true
        );
        $this->indexerRegistry->get('cataloginventory_stock')->reindexAll();
        $this->cacheManager->clean(['config', 'full_page', 'prostor_loyalty']);

        $output->writeln('<info>Prostor demo customer, products, stock, and configuration are ready.</info>');

        return Command::SUCCESS;
    }

    private function saveConfiguration(int $websiteId): void
    {
        $values = [
            'enabled' => '1',
            'base_url' => 'http://loyalty-mock:8080',
            'token' => $this->encryptor->encrypt('local-demo-token'),
            'timeout' => '10',
            'cache_ttl' => '300',
            'loyalty_currency' => 'UAH',
            'thresholds' => '[{"minimum_spend":"4000","percentage":"3"},{"minimum_spend":"8000","percentage":"5"}]',
        ];

        foreach ($values as $field => $value) {
            $this->configWriter->save(
                self::CONFIG_PREFIX . $field,
                $value,
                ScopeInterface::SCOPE_WEBSITES,
                $websiteId
            );
        }
    }

    private function createCustomer(int $websiteId): void
    {
        try {
            $this->customerRepository->get(self::CUSTOMER_EMAIL, $websiteId);

            return;
        } catch (NoSuchEntityException) {
        }

        $customer = $this->customerFactory->create();
        $customer->setWebsiteId($websiteId);
        $customer->setEmail(self::CUSTOMER_EMAIL);
        $customer->setFirstname('Demo');
        $customer->setLastname('Reviewer');

        $this->accountManagement->createAccount($customer, self::CUSTOMER_PASSWORD);
    }

    private function saveProduct(
        int $websiteId,
        string $sku,
        string $name,
        string $price,
        bool $promoExcluded
    ): void {
        try {
            $product = $this->productRepository->get($sku, false, null, true);
        } catch (NoSuchEntityException) {
            $product = $this->productFactory->create();
            $product->setAttributeSetId($product->getDefaultAttributeSetId());
        }

        $product->setSku($sku);
        $product->setName($name);
        $product->setUrlKey($sku);
        $product->setTypeId(Product\Type::TYPE_SIMPLE);
        $product->setPrice($price);
        $product->setStatus(Status::STATUS_ENABLED);
        $product->setVisibility(Visibility::VISIBILITY_BOTH);
        $product->setWebsiteIds([$websiteId]);
        $product->setData('promo_excluded', $promoExcluded ? 1 : 0);

        $this->productRepository->save($product);

        $sourceItem = $this->sourceItemFactory->create();
        $sourceItem->setSourceCode($this->defaultSourceProvider->getCode());
        $sourceItem->setSku($sku);
        $sourceItem->setQuantity(self::PRODUCT_QUANTITY);
        $sourceItem->setStatus(SourceItemInterface::STATUS_IN_STOCK);

        $this->sourceItemsSave->execute([$sourceItem]);
    }
}
