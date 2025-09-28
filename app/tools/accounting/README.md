# Accounting Toolkit

Helper scripts for the NederAI bookkeeping schema.

## `setup_accounting.php`

Run this script from the project root to (re)apply the PostgreSQL objects that back the accounting subsystem.

```
php app/tools/accounting/setup_accounting.php
```

## Schema files

- `app/database/accounting.sql` contains the tables for organizations, accounts, entries, and journal lines.
- `app/database/rgs_classic.sql` provides a reference dataset in the `rgs.rgs_3_7` table.

## Service layer

Controllers should resolve `App\Services\Accounting\AccountingService` via the container. The service exposes helpers for listing organizations, managing accounts, and creating balanced journal entries that follow the new schema.

Example:

```php
$accounting = $container->get(App\Services\Accounting\AccountingService::class);

$accounting->createAccount($orgId, [
    'code' => '1000',
    'name' => 'Bank',
    'type' => 'asset',
]);

$accounting->createEntry($orgId, [
    'entry_date' => '2025-09-01',
    'reference' => 'MEM-2025-001',
], [
    ['direction' => 'debit', 'account_code' => '1000', 'amount' => 100],
    ['direction' => 'credit', 'account_code' => '2600', 'amount' => 100],
]);
```
