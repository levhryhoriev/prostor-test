<?php

declare(strict_types=1);

namespace Prostor\CumDiscount\Test\Unit\Model\Loyalty;

use DateTimeImmutable;
use Magento\Framework\Validator\Currency as CurrencyValidator;
use PHPUnit\Framework\TestCase;
use Prostor\CumDiscount\Model\Cache\Type;
use Prostor\CumDiscount\Model\Config\LoyaltyConfig;
use Prostor\CumDiscount\Model\Config\LoyaltyConfiguration;
use Prostor\CumDiscount\Model\Discount\Threshold;
use Prostor\CumDiscount\Model\Discount\ThresholdTable;
use Prostor\CumDiscount\Model\Loyalty\CacheBackedLoyaltySnapshotProvider;
use Prostor\CumDiscount\Model\Loyalty\LoyaltyHttpClient;
use Prostor\CumDiscount\Model\Loyalty\LoyaltySnapshot;
use Prostor\CumDiscount\Model\Loyalty\LoyaltySnapshotPayloadParser;
use Psr\Log\LoggerInterface;

class CacheBackedLoyaltySnapshotProviderTest extends TestCase
{
    public function testSequentialRequestsReuseTheValidatedCachedSnapshot(): void
    {
        $currencyValidator = $this->createStub(CurrencyValidator::class);
        $currencyValidator->method('isValid')->with('UAH')->willReturn(true);
        $snapshot = new LoyaltySnapshot(
            $currencyValidator,
            42,
            8123.50,
            'UAH',
            new DateTimeImmutable('2026-01-01T00:00:00Z')
        );
        $config = $this->createStub(LoyaltyConfig::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('read')->willReturn($this->configuration());
        $client = $this->createMock(LoyaltyHttpClient::class);
        $client->expects(self::once())->method('fetch')->willReturn($snapshot);
        $cache = $this->createMock(Type::class);
        $cache->expects(self::exactly(2))->method('load')->willReturnOnConsecutiveCalls(false, '{"cached":true}');
        $cache->expects(self::once())->method('save')->willReturn(true);
        $parser = $this->createMock(LoyaltySnapshotPayloadParser::class);
        $parser->expects(self::once())->method('parseCachedPayload')->with('{"cached":true}')->willReturn($snapshot);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');
        $provider = new CacheBackedLoyaltySnapshotProvider($config, $client, $cache, $logger, $parser);

        self::assertSame($snapshot, $provider->getSnapshot(42, 7));
        self::assertSame($snapshot, $provider->getSnapshot(42, 7));
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
            new ThresholdTable([new Threshold(4000.0, 3.0)])
        );
    }
}
