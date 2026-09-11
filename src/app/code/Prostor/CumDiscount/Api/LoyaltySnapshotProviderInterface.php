<?php

declare(strict_types=1);

namespace Prostor\CumDiscount\Api;

use Prostor\CumDiscount\Model\Loyalty\LoyaltySnapshot;

interface LoyaltySnapshotProviderInterface
{
    public function getSnapshot(int $customerId, int $websiteId): ?LoyaltySnapshot;
}
