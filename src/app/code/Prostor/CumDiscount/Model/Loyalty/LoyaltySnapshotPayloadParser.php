<?php

declare(strict_types=1);

namespace Prostor\CumDiscount\Model\Loyalty;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

class LoyaltySnapshotPayloadParser
{
    public function __construct(private readonly LoyaltySnapshotFactory $snapshotFactory)
    {
    }

    public function parseApiResponse(string $body): LoyaltySnapshot
    {
        $payload = $this->decode($body);
        if (($payload['window_days'] ?? null) !== 90) {
            throw new InvalidArgumentException('The loyalty payload is invalid.');
        }

        return $this->snapshot($payload);
    }

    public function parseCachedPayload(string $body): LoyaltySnapshot
    {
        return $this->snapshot($this->decode($body));
    }

    private function decode(string $body): array
    {
        try {
            $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException('The loyalty payload is invalid.', 0, $exception);
        }

        if (!is_array($payload)) {
            throw new InvalidArgumentException('The loyalty payload is invalid.');
        }

        return $payload;
    }

    private function snapshot(array $payload): LoyaltySnapshot
    {
        $customerId = $payload['customer_id'] ?? null;
        $currency = $payload['currency'] ?? null;
        $asOf = $payload['as_of'] ?? null;
        $amount = $this->decimal($payload['spent_amount'] ?? null);

        if (!is_int($customerId) || !is_string($currency) || !is_string($asOf) || $amount === null) {
            throw new InvalidArgumentException('The loyalty payload is invalid.');
        }

        return $this->snapshotFactory->create(
            [
                'customerId' => $customerId,
                'spentAmount' => $amount,
                'currency' => $currency,
                'asOf' => $this->asOf($asOf),
            ]
        );
    }

    private function decimal(mixed $value): ?float
    {
        if (is_string($value)) {
            if (preg_match('/\A(?:0|[1-9][0-9]*)(?:\.[0-9]{1,4})?\z/D', $value) !== 1) {
                return null;
            }

            return (float) $value;
        }

        if (is_int($value)) {
            return $value < 0 ? null : (float) $value;
        }

        if (!is_float($value) || !is_finite($value) || $value < 0.0 || abs($value - round($value, 4)) > 0.00000001) {
            return null;
        }

        return $value;
    }

    private function asOf(string $value): DateTimeImmutable
    {
        if (preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})\z/D', $value) !== 1) {
            throw new InvalidArgumentException('The as-of timestamp is invalid.');
        }

        try {
            $asOf = new DateTimeImmutable($value);
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException('The as-of timestamp is invalid.', 0, $exception);
        }

        if ($asOf->format('Y-m-d\\TH:i:s') !== substr($value, 0, 19)
            || $asOf->getTimezone()->getOffset($asOf) !== 0
            || $asOf > new DateTimeImmutable('now', new DateTimeZone('UTC'))) {
            throw new InvalidArgumentException('The as-of timestamp is invalid.');
        }

        return $asOf->setTimezone(new DateTimeZone('UTC'));
    }
}
