<?php

declare(strict_types=1);

namespace Prostor\CumDiscount\Logger;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger as MonologLogger;

class Handler extends Base
{
    protected $fileName = '/var/log/prostor_cumdiscount.log';

    protected $loggerType = MonologLogger::WARNING;
}
