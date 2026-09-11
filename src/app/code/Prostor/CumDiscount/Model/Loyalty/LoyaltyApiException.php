<?php

declare(strict_types=1);

namespace Prostor\CumDiscount\Model\Loyalty;

use RuntimeException;

class LoyaltyApiException extends RuntimeException
{
    private const CATEGORIES = ['transport', 'response', 'validation'];

    public function __construct(private readonly string $category)
    {
        parent::__construct('The loyalty request failed.');
    }

    public function category(): string
    {
        return in_array($this->category, self::CATEGORIES, true) ? $this->category : 'validation';
    }
}
