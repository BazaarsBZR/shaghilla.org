<?php

$path = dirname(__DIR__).'/vendor/laravel/framework/config/database.php';

if (! is_file($path)) {
    exit(0);
}

$contents = file_get_contents($path);

if ($contents === false || str_contains($contents, "defined('Pdo\\\\Mysql::ATTR_SSL_CA')")) {
    exit(0);
}

$legacy = 'PDO::MYSQL_ATTR_SSL_CA';

if (! str_contains($contents, $legacy)) {
    exit(0);
}

$replacement = "(defined('Pdo\\\\Mysql::ATTR_SSL_CA') ? constant('Pdo\\\\Mysql::ATTR_SSL_CA') : constant('PDO::MYSQL_ATTR_SSL_CA'))";
$patched = str_replace($legacy, $replacement, $contents);

if (file_put_contents($path, $patched) === false) {
    fwrite(STDERR, "Unable to apply the Laravel PHP 8.5 PDO compatibility patch.\n");
    exit(1);
}
