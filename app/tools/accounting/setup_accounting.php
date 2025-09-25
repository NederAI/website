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

$schemaFile = __DIR__ . '/../../database/accounting_schema.sql';
if (!is_readable($schemaFile)) {
    fwrite(STDERR, "Cannot read schema file at {$schemaFile}\n");
    exit(1);
}

$sql = file_get_contents($schemaFile);
$pdo->exec($sql);

fwrite(STDOUT, "Accounting schema applied successfully.\n");

$options = [
    'csv' => null,
    'delimiter' => ';',
    'version_tag' => null,
];

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--csv=')) {
        $options['csv'] = substr($arg, 6);
    } elseif (str_starts_with($arg, '--delimiter=')) {
        $options['delimiter'] = substr($arg, 12);
    } elseif (str_starts_with($arg, '--version=')) {
        $options['version_tag'] = substr($arg, 10);
    }
}

if ($options['csv']) {
    /** @var \App\Services\Accounting\RgsRepository $repo */
    $repo = $container->get(App\Services\Accounting\RgsRepository::class);
    $result = $repo->importFromCsv($options['csv'], [
        'delimiter' => $options['delimiter'],
        'version_tag' => $options['version_tag'],
    ]);
    fwrite(STDOUT, sprintf(
        "Imported RGS codes from %s (inserted %d, updated %d).\n",
        $options['csv'],
        $result['inserted'],
        $result['updated']
    ));
}
