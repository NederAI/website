# Accounting Toolkit

This folder contains helper scripts for the bookkeeping subsystem.

## `setup_accounting.php`

Run this script from the project root to create or update the PostgreSQL objects that power the double-entry bookkeeping system.

```
php app/tools/accounting/setup_accounting.php
```

Pass a ReferentieGrootboekSchema CSV file to seed the master list of RGS codes. The importer assumes a semicolon separated file. Adjust the delimiter with `--delimiter` if needed.

```
php app/tools/accounting/setup_accounting.php --csv=/path/to/RGS.csv --version=7.0
```

The script prints how many RGS rows were inserted or updated.

## CSV expectations

The importer expects at least the following columns (case insensitive):

- `RGS-code` (or `code`)
- `Omschrijving` / `Description`

Optional columns improve the mapping: `niveau`, `rgs-code-ouder`, `balansmutatiesoort`, `functie`, and `boekbaar`.

## Using the service layer

Within controllers or other services resolve `App\Services\Accounting\AccountingService` via the container:

```php
$ledger = $container->get(App\Services\Accounting\AccountingService::class);

$ledger->ensureAccount([
    'code' => '1000',
    'name' => 'Kas',
    'rgs_code' => 'B1',
]);

$ledger->createJournalEntry([
    'journal_code' => 'MEM',
    'entry_date' => '2025-09-01',
    'reference' => 'MEM-2025-001',
], [
    ['account_code' => '1000', 'direction' => 'debit', 'amount' => 100.00],
    ['account_code' => '1600', 'direction' => 'credit', 'amount' => 100.00],
]);
```

The `trial_balance` view in PostgreSQL is available for reporting or API endpoints.

## Intranet UI

Accounting operations are also available via the internal UI at https://intranet.neder.ai (login required). Use your intranet credentials to work with the ledger, create journal entries, and inspect the trial balance there.
