<?php

declare(strict_types=1);

namespace Prostor\CumDiscount\Test\Unit\Model\Loyalty;

use DateTimeImmutable;
use Magento\Framework\HTTP\ClientFactory;
use Magento\Framework\HTTP\ClientInterface;
use Magento\Framework\Validator\Currency as CurrencyValidator;
use PHPUnit\Framework\TestCase;
use Prostor\CumDiscount\Model\Config\LoyaltyConfiguration;
use Prostor\CumDiscount\Model\Discount\Threshold;
use Prostor\CumDiscount\Model\Discount\ThresholdTable;
use Prostor\CumDiscount\Model\Loyalty\LoyaltyApiException;
use Prostor\CumDiscount\Model\Loyalty\LoyaltyHttpClient;
use Prostor\CumDiscount\Model\Loyalty\LoyaltySnapshot;
use Prostor\CumDiscount\Model\Loyalty\LoyaltySnapshotPayloadParser;

class LoyaltyHttpClientTest extends TestCase
{
    public function testItReturnsTheValidatedHttpSnapshot(): void
    {
        [$factory, $client, $parser] = $this->dependencies();
        $client->method('getStatus')->willReturn(200);
        $client->method('getBody')->willReturn('{"window_days":90}');
        $snapshot = $this->snapshot();
        $parser->expects(self::once())->method('parseApiResponse')->willReturn($snapshot);

        self::assertSame($snapshot, (new LoyaltyHttpClient($factory, $parser))->fetch($this->configuration(), 42));
    }

    public function testItClassifiesServerAndTimeoutFailuresWithoutExposingPayloads(): void
    {
        [$factory, $client, $parser] = $this->dependencies();
        $client->method('getStatus')->willReturn(500);
        $client->method('getBody')->willReturn('{}');
        $this->assertCategory(new LoyaltyHttpClient($factory, $parser), 'response');

        [$factory, $client, $parser] = $this->dependencies();
        $client->method('get')->willThrowException(new \RuntimeException('timeout'));
        $this->assertCategory(new LoyaltyHttpClient($factory, $parser), 'transport');
    }

    public function testItClassifiesInvalidResponsesAsValidationFailures(): void
    {
        [$factory, $client, $parser] = $this->dependencies();
        $client->method('getStatus')->willReturn(200);
        $client->method('getBody')->willReturn('{invalid');
        $parser->method('parseApiResponse')->willThrowException(new \InvalidArgumentException('invalid'));

        $this->assertCategory(new LoyaltyHttpClient($factory, $parser), 'validation');
    }

    private function assertCategory(LoyaltyHttpClient $client, string $category): void
    {
        try {
            $client->fetch($this->configuration(), 42);
        } catch (LoyaltyApiException $exception) {
            self::assertSame($category, $exception->category());
            return;
        }

        self::fail('A loyalty exception was expected.');
    }

    private function dependencies(): array
    {
        $factory = $this->createStub(ClientFactory::class);
        $client = $this->createStub(ClientInterface::class);
        $factory->method('create')->willReturn($client);

        return [$factory, $client, $this->createMock(LoyaltySnapshotPayloadParser::class)];
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
            new ThresholdTable([])
        );
    }

    private function snapshot(): LoyaltySnapshot
    {
        $currencyValidator = $this->createStub(CurrencyValidator::class);
        $currencyValidator->method('isValid')->with('UAH')->willReturn(true);

        return new LoyaltySnapshot(
            $currencyValidator,
            42,
            8123.50,
            'UAH',
            new DateTimeImmutable('2026-01-01T00:00:00Z')
        );
    }
}
