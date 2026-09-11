<?php

declare(strict_types=1);

const RESPONSES = [
    'normal' => '8123.50',
    'below-threshold' => '3500.00',
];

$method = $_SERVER['REQUEST_METHOD'] ?? '';
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($requestUri, PHP_URL_PATH);
$query = parse_url($requestUri, PHP_URL_QUERY);

if ($method !== 'GET') {
    respond(405);
}

if ($path === '/health' && $query === null) {
    respond(200, 'ok');
}

if ($path === '/request-count' && $query === null) {
    respond(
        200,
        json_encode(['count' => requestCount()], JSON_THROW_ON_ERROR),
        'application/json'
    );
}

if ($path !== '/loyalty/cumulative') {
    respond(404);
}

$parameters = queryParameters($query);
$customerId = $parameters['customer_id'] ?? null;
$windowDays = $parameters['window_days'] ?? null;
$authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

if (count($parameters) !== 2
    || array_diff(array_keys($parameters), ['customer_id', 'window_days']) !== []
    || !is_string($customerId)
    || !ctype_digit($customerId)
    || (int) $customerId < 1
    || $windowDays !== '90'
    || preg_match('/^Bearer\\s+\\S+$/D', $authorization) !== 1) {
    respond(400);
}

incrementRequestCount();

$mode = getenv('LOYALTY_MOCK_MODE') ?: 'normal';

if ($mode === 'timeout') {
    sleep(15);
} elseif ($mode === 'http-500') {
    respond(500);
} elseif (!array_key_exists($mode, RESPONSES)) {
    respond(503);
}

$body = '{"customer_id":' . $customerId
    . ',"window_days":90,"spent_amount":' . RESPONSES[$mode]
    . ',"currency":"UAH","as_of":"2025-09-30T12:00:00Z"}';
respond(200, $body, 'application/json');

function queryParameters(string|false|null $query): array
{
    if (!is_string($query) || $query === '') {
        return [];
    }

    $parameters = [];

    foreach (explode('&', $query) as $pair) {
        $separator = strpos($pair, '=');

        if ($separator === false) {
            return [];
        }

        $name = rawurldecode(substr($pair, 0, $separator));

        if (array_key_exists($name, $parameters)) {
            return [];
        }

        $parameters[$name] = rawurldecode(substr($pair, $separator + 1));
    }

    return $parameters;
}

function respond(int $status, string $body = '', string $contentType = 'text/plain'): never
{
    http_response_code($status);
    header('Content-Type: ' . $contentType);
    header('Content-Length: ' . strlen($body));
    echo $body;
    exit;
}

function requestCountPath(): string
{
    return sys_get_temp_dir() . '/loyalty-mock-request-count';
}

function requestCount(): int
{
    $handle = fopen(requestCountPath(), 'c+');

    if ($handle === false) {
        return 0;
    }

    flock($handle, LOCK_SH);
    $contents = stream_get_contents($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    return is_string($contents) && ctype_digit(trim($contents)) ? (int) trim($contents) : 0;
}

function incrementRequestCount(): void
{
    $handle = fopen(requestCountPath(), 'c+');

    if ($handle === false) {
        return;
    }

    flock($handle, LOCK_EX);
    rewind($handle);
    $contents = stream_get_contents($handle);
    $count = is_string($contents) && ctype_digit(trim($contents)) ? (int) trim($contents) : 0;
    rewind($handle);
    ftruncate($handle, 0);
    fwrite($handle, (string) ($count + 1));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}
