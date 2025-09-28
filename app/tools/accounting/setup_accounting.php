#!/usr/bin/env php
<?php

require __DIR__ . '/../../../core/Container.php';

$container = new Core\Container();
$container->registerNamespaces([
    'Core' => __DIR__ . '/../../../core/',
    'App'  => __DIR__ . '/../../../app/',
    'Lib'  => __DIR__ . '/../../../lib/',
]);

$database = $container->get(Core\Database::class);
$pdo = $database->getConnection();

$schemaFile = __DIR__ . '/../../database/accounting.sql';
if (!is_readable($schemaFile)) {
    fwrite(STDERR, "Cannot read schema file at {$schemaFile}\n");
    exit(1);
}

$sql = file_get_contents($schemaFile);
$pdo->exec($sql);

fwrite(STDOUT, "Accounting schema applied successfully.\n");
