<?php

declare(strict_types=1);

namespace Prostor\CumDiscount\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Validator\Currency as CurrencyValidator;
use Magento\Framework\Validator\Url as UrlValidator;
use Magento\Store\Model\ScopeInterface;

class LoyaltyConfig
{
    private const PATH_PREFIX = 'prostor_cumdiscount/general/';

    private const MAX_TIMEOUT_SECONDS = 30;

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor,
        private readonly CurrencyValidator $currencyValidator,
        private readonly UrlValidator $urlValidator,
        private readonly LoyaltyConfigurationMapper $configurationMapper
    ) {
    }

    public function isEnabled(int $websiteId): bool
    {
        $value = $this->value('enabled', $websiteId);

        if ($value === '1') {
            return true;
        }

        if ($value === '0') {
            return false;
        }

        throw new InvalidLoyaltyConfiguration('The enabled setting is invalid.');
    }

    public function read(int $websiteId): LoyaltyConfiguration
    {
        return $this->configurationMapper->map(
            $this->isEnabled($websiteId),
            $this->baseUrl($websiteId),
            $this->token($websiteId),
            $this->timeout($websiteId),
            $this->positiveInteger('cache_ttl', $websiteId),
            $this->currency($websiteId),
            $this->requiredString('thresholds', $websiteId)
        );
    }

    private function baseUrl(int $websiteId): string
    {
        $baseUrl = rtrim($this->requiredString('base_url', $websiteId), '/');
        if ($this->urlValidator->isValid($baseUrl, ['https'])) {
            return $baseUrl;
        }

        if (!$this->urlValidator->isValid($baseUrl, ['http']) || !$this->isAllowedLocalHttpUrl($baseUrl)) {
            throw new InvalidLoyaltyConfiguration('The base URL must use HTTPS.');
        }

        return $baseUrl;
    }

    private function isAllowedLocalHttpUrl(string $baseUrl): bool
    {
        $parts = parse_url($baseUrl);

        return is_array($parts)
            && ($parts['scheme'] ?? null) === 'http'
            && ($parts['host'] ?? null) === 'loyalty-mock'
            && ($parts['port'] ?? null) === 8080
            && !isset($parts['path'], $parts['query'], $parts['fragment'], $parts['user'], $parts['pass'])
            && getenv('PROSTOR_CUMDISCOUNT_LOCAL_DOCKER') === '1';
    }

    private function token(int $websiteId): string
    {
        $encryptedToken = $this->requiredString('token', $websiteId);
        $token = trim($this->encryptor->decrypt($encryptedToken));

        if ($token === '') {
            throw new InvalidLoyaltyConfiguration('The token is empty.');
        }

        return $token;
    }

    private function positiveInteger(string $field, int $websiteId): int
    {
        $value = $this->requiredString($field, $websiteId);

        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($integer === false) {
            throw new InvalidLoyaltyConfiguration(sprintf('The %s setting is invalid.', $field));
        }

        return $integer;
    }

    private function timeout(int $websiteId): int
    {
        $timeout = $this->positiveInteger('timeout', $websiteId);

        if ($timeout > self::MAX_TIMEOUT_SECONDS) {
            throw new InvalidLoyaltyConfiguration('The timeout setting is invalid.');
        }

        return $timeout;
    }

    private function currency(int $websiteId): string
    {
        $currency = $this->requiredString('loyalty_currency', $websiteId);

        if (!$this->currencyValidator->isValid($currency)) {
            throw new InvalidLoyaltyConfiguration('The loyalty currency is invalid.');
        }

        return $currency;
    }

    private function requiredString(string $field, int $websiteId): string
    {
        $value = $this->value($field, $websiteId);

        if (!is_string($value) || trim($value) === '') {
            throw new InvalidLoyaltyConfiguration(sprintf('The %s setting is required.', $field));
        }

        return trim($value);
    }

    private function value(string $field, int $websiteId): mixed
    {
        if ($websiteId < 1) {
            throw new InvalidLoyaltyConfiguration('The website identifier is invalid.');
        }

        return $this->scopeConfig->getValue(
            self::PATH_PREFIX . $field,
            ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        );
    }
}
