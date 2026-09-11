<?php

declare(strict_types=1);

namespace Prostor\CumDiscount\Model\Discount;

use Magento\Framework\Serialize\SerializerInterface;

class ThresholdConfigurationMapper
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly ThresholdFactory $thresholdFactory,
        private readonly ThresholdTableFactory $thresholdTableFactory
    ) {
    }

    public function map(string $serializedRows): ThresholdTable
    {
        try {
            $rows = $this->serializer->unserialize($serializedRows);
        } catch (\Throwable $exception) {
            throw new InvalidThresholdConfiguration('The threshold table is invalid.', 0, $exception);
        }

        if (!is_array($rows) || $rows === []) {
            throw new InvalidThresholdConfiguration('The threshold table is invalid.');
        }

        $thresholds = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidThresholdConfiguration('Each threshold row must be an array.');
            }

            $minimumSpend = $this->decimal($row['minimum_spend'] ?? null);
            $percentage = $this->decimal($row['percentage'] ?? null);
            if ($minimumSpend === null || $percentage === null || $percentage > 100.0) {
                throw new InvalidThresholdConfiguration('Each threshold row must contain valid values.');
            }

            $thresholds[] = $this->thresholdFactory->create(
                ['minimumSpend' => $minimumSpend, 'percentage' => $percentage]
            );
        }

        try {
            return $this->thresholdTableFactory->create(['thresholds' => $thresholds]);
        } catch (\Throwable $exception) {
            throw new InvalidThresholdConfiguration('The threshold table is invalid.', 0, $exception);
        }
    }

    private function decimal(mixed $value): ?float
    {
        if (!is_string($value) || preg_match('/\A(?:0|[1-9][0-9]*)(?:\.[0-9]{1,4})?\z/D', $value) !== 1) {
            return null;
        }

        return (float) $value;
    }
}
