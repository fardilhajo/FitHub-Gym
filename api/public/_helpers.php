<?php
/**
 * Shared helpers for the read-only public REST API.
 */

function public_api_cors(array $methods = ['GET']): void
{
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: ' . implode(', ', array_merge($methods, ['OPTIONS'])));
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 86400');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function public_api_require_get(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        public_api_error('method_not_allowed', 'Only GET requests are supported by the public API.', 405);
    }
}

function public_api_json_headers(): void
{
    header('Content-Type: application/json; charset=utf-8');
}

function public_api_success($data, array $meta = [], int $status = 200): void
{
    http_response_code($status);
    $payload = ['data' => $data];
    if ($meta !== []) {
        $payload['meta'] = $meta;
    }
    echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function public_api_error(string $code, string $message, int $status = 400, array $details = []): void
{
    http_response_code($status);
    $payload = [
        'error' => [
            'code' => $code,
            'message' => $message,
        ],
    ];
    if ($details !== []) {
        $payload['error']['details'] = $details;
    }
    echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function public_api_int_param(string $name, ?int $default = null, int $min = 0, ?int $max = null): ?int
{
    if (!isset($_GET[$name]) || $_GET[$name] === '') {
        return $default;
    }

    $value = filter_var($_GET[$name], FILTER_VALIDATE_INT);
    if ($value === false || $value < $min || ($max !== null && $value > $max)) {
        $range = $max === null ? "at least $min" : "between $min and $max";
        public_api_error('invalid_parameter', "Parameter '$name' must be an integer $range.");
    }

    return $value;
}

function public_api_string_param(string $name): string
{
    return trim((string)($_GET[$name] ?? ''));
}

function public_api_enum_param(string $name, array $allowedValues): string
{
    $value = public_api_string_param($name);
    if ($value === '') {
        return '';
    }

    if (!in_array($value, $allowedValues, true)) {
        public_api_error('invalid_parameter', "Parameter '$name' has an unsupported value.", 400, [
            'allowed_values' => $allowedValues,
        ]);
    }

    return $value;
}

function public_api_datetime_param(string $name): ?DateTimeImmutable
{
    $value = public_api_string_param($name);
    if ($value === '') {
        return null;
    }

    try {
        return new DateTimeImmutable($value);
    } catch (Exception $e) {
        public_api_error('invalid_parameter', "Parameter '$name' must be a valid date/time, for example 2026-05-30T10:00.");
    }
}

function public_api_sql_datetime(DateTimeImmutable $date): string
{
    return $date->format('Y-m-d H:i:s');
}

function public_api_csv_list(?string $value): array
{
    if ($value === null || trim($value) === '') {
        return [];
    }

    return array_values(array_filter(array_map('trim', explode(',', $value)), static fn ($item) => $item !== ''));
}

function public_api_float_or_null(mixed $value): ?float
{
    return $value === null ? null : (float)$value;
}
