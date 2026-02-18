<?php
declare(strict_types=1);

if (!extension_loaded('pdo_mysql')) {
    throw new RuntimeException('PDO MySQL driver is not installed.');
}

$config = require __DIR__ . '/config.php';

$db = $config['db'];
$dsn = sprintf(
    'mysql:host=%s;%sdbname=%s;charset=%s',
    $db['host'],
    !empty($db['port']) ? 'port=' . $db['port'] . ';' : '',
    $db['name'],
    $db['charset']
);

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

return new PDO($dsn, $db['user'], $db['pass'], $options);
