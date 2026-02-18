<?php
declare(strict_types=1);

$envFile = __DIR__ . '/.env';
if (is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($value !== '' && (
            ($value[0] === '"' && substr($value, -1) === '"')
            || ($value[0] === "'" && substr($value, -1) === "'")
        )) {
            $value = substr($value, 1, -1);
        }
        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

$env = static function (string $key, ?string $default = null): ?string {
    if (array_key_exists($key, $_ENV)) {
        $value = $_ENV[$key];
        if ($value !== null && $value !== '') {
            return (string) $value;
        }
    }
    if (array_key_exists($key, $_SERVER)) {
        $value = $_SERVER[$key];
        if ($value !== null && $value !== '') {
            return (string) $value;
        }
    }
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return $value;
};

$dbUrl = $env('DATABASE_URL') ?? $env('MYSQL_URL');
$parsed = [];
if ($dbUrl) {
    $parsed = parse_url($dbUrl) ?: [];
}

$host = $env('MYSQLHOST')
    ?? $env('DB_HOST')
    ?? ($parsed['host'] ?? '127.0.0.1');

$port = $env('MYSQLPORT')
    ?? $env('DB_PORT')
    ?? ($parsed['port'] ?? null);

$name = $env('MYSQLDATABASE')
    ?? $env('DB_NAME')
    ?? (isset($parsed['path']) ? ltrim((string) $parsed['path'], '/') : 'crud_app');

$user = $env('MYSQLUSER')
    ?? $env('DB_USER')
    ?? ($parsed['user'] ?? 'root');

$pass = $env('MYSQLPASSWORD')
    ?? $env('DB_PASS')
    ?? ($parsed['pass'] ?? '');

$charset = $env('DB_CHARSET') ?? 'utf8mb4';

if ($host === 'localhost') {
    $host = '127.0.0.1';
}

return [
    'db' => [
        'host' => $host,
        'port' => $port,
        'name' => $name,
        'user' => $user,
        'pass' => $pass,
        'charset' => $charset,
    ],
];
