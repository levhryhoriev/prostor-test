<?php

declare(strict_types=1);

namespace Prostor\CumDiscount\Model\Loyalty;

use DateTimeImmutable;
use InvalidArgumentException;
use Magento\Framework\Validator\Currency as CurrencyValidator;

class LoyaltySnapshot
{
    public function __construct(
        private readonly CurrencyValidator $currencyValidator,
        private readonly int $customerId,
        private readonly float $spentAmount,
        private readonly string $currency,
        private readonly DateTimeImmutable $asOf
    ) {
        if ($this->customerId < 1 || $this->spentAmount < 0.0) {
            throw new InvalidArgumentException('The loyalty snapshot is invalid.');
        }

        if (!$this->currencyValidator->isValid($this->currency)) {
            throw new InvalidArgumentException('The currency is invalid.');
        }
    }

    public function customerId(): int
    {
        return $this->customerId;
    }

    public function spentAmount(): float
    {
        return $this->spentAmount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function asOf(): string
    {
        return $this->asOf->format(DATE_RFC3339);
    }

    public function toCachePayload(): array
    {
        return [
            'customer_id' => $this->customerId,
            'spent_amount' => sprintf('%.4F', $this->spentAmount),
            'currency' => $this->currency,
            'as_of' => $this->asOf(),
        ];
    }
}
