<?php
namespace App\Services\Accounting;

use Core\Container;
use Core\Database;
use InvalidArgumentException;
use PDO;

class AccountingService {
    private PDO $pdo;
    private RgsRepository $rgs;

    public function __construct(Container $container) {
        $this->pdo = $container->get(Database::class)->getConnection();
        $this->rgs = $container->get(RgsRepository::class);
    }

    public function ensureJournal(array $payload): array {
        $code = strtoupper(trim((string) ($payload['code'] ?? '')));
        if ($code === '') {
            throw new InvalidArgumentException('Journal code is required.');
        }

        $existing = $this->findJournalByCode($code);
        $name = trim((string) ($payload['name'] ?? ($existing['name'] ?? $code)));
        if ($name === '') {
            $name = $code;
        }
        $description = $payload['description'] ?? ($existing['description'] ?? null);
        $currency = strtoupper(trim((string) ($payload['default_currency'] ?? ($existing['default_currency'] ?? 'EUR'))));
        if ($currency === '') {
            $currency = 'EUR';
        }
        $allowManual = array_key_exists('allow_manual', $payload)
            ? (bool) $payload['allow_manual']
            : (isset($existing['allow_manual']) ? (bool) $existing['allow_manual'] : true);
        $metadata = $this->encodeMetadata($payload['metadata'] ?? ($existing['metadata'] ?? []));

        $stmt = $this->pdo->prepare(
            "INSERT INTO accounting.journals (code, name, description, default_currency, allow_manual, metadata)
             VALUES (:code, :name, :description, :currency, :allow_manual, :metadata)
             ON CONFLICT (code) DO UPDATE SET
                name = EXCLUDED.name,
                description = EXCLUDED.description,
                default_currency = EXCLUDED.default_currency,
                allow_manual = EXCLUDED.allow_manual,
                metadata = EXCLUDED.metadata,
                updated_at = now()
             RETURNING *"
        );
        $stmt->execute([
            ':code' => $code,
            ':name' => $name,
            ':description' => $description,
            ':currency' => $currency,
            ':allow_manual' => $allowManual ? 't' : 'f',
            ':metadata' => $metadata,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function ensureAccount(array $payload): array {
        $code = trim((string) ($payload['code'] ?? ''));
        if ($code === '') {
            throw new InvalidArgumentException('Account code is required.');
        }
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Account name is required.');
        }

        $rgsCode = isset($payload['rgs_code']) ? strtoupper(trim((string) $payload['rgs_code'])) : null;
        $accountType = isset($payload['account_type']) ? strtolower(trim((string) $payload['account_type'])) : null;
        if ($rgsCode !== null && $rgsCode !== '') {
            $rgsRecord = $this->rgs->getByCode($rgsCode);
            if ($rgsRecord === null) {
                throw new InvalidArgumentException("Unknown RGS code: {$rgsCode}");
            }
            if ($accountType === null || $accountType === '') {
                $accountType = $rgsRecord['account_type'];
            }
        }
        if ($accountType === null || $accountType === '') {
            $accountType = 'memo';
        }
        $accountType = $this->normaliseAccountType($accountType);

        $currency = strtoupper(trim((string) ($payload['currency'] ?? 'EUR')));
        if ($currency === '') {
            $currency = 'EUR';
        }
        $metadata = $this->encodeMetadata($payload['metadata'] ?? []);

        $stmt = $this->pdo->prepare(
            "INSERT INTO accounting.ledger_accounts (
                code, name, rgs_code, account_type, currency, metadata
            ) VALUES (
                :code, :name, :rgs_code, :account_type, :currency, :metadata
            )
            ON CONFLICT (code) DO UPDATE SET
                name = EXCLUDED.name,
                rgs_code = EXCLUDED.rgs_code,
                account_type = EXCLUDED.account_type,
                currency = EXCLUDED.currency,
                metadata = EXCLUDED.metadata,
                updated_at = now()
            RETURNING *"
        );
        $stmt->execute([
            ':code' => $code,
            ':name' => $name,
            ':rgs_code' => $rgsCode ?: null,
            ':account_type' => $accountType,
            ':currency' => $currency,
            ':metadata' => $metadata,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createJournalEntry(array $entryData, array $lines, bool $autoPost = false): array {
        if (empty($lines) || count($lines) < 2) {
            throw new InvalidArgumentException('A journal entry needs at least two lines.');
        }
        $this->validateLines($lines);

        $journal = $this->resolveJournal($entryData);
        $entryDate = $entryData['entry_date'] ?? null;
        if ($entryDate === null) {
            throw new InvalidArgumentException('Entry date is required.');
        }
        $currency = strtoupper(trim((string) ($entryData['currency'] ?? $journal['default_currency'] ?? 'EUR')));
        if ($currency === '') {
            $currency = 'EUR';
        }
        $status = strtolower(trim((string) ($entryData['status'] ?? 'draft')));
        if (!in_array($status, ['draft', 'posted', 'void'], true)) {
            throw new InvalidArgumentException('Status must be draft, posted, or void.');
        }
        if ($autoPost) {
            $status = 'posted';
        }

        $metadata = $this->encodeMetadata($entryData['metadata'] ?? []);
        $reference = $entryData['reference'] ?? null;
        $description = $entryData['description'] ?? null;
        $exchangeRate = $entryData['exchange_rate'] ?? 1.0;
        if (!is_numeric($exchangeRate) || $exchangeRate <= 0) {
            throw new InvalidArgumentException('Exchange rate must be a positive number.');
        }

        $delta = 0.0;
        foreach ($lines as $line) {
            $direction = strtolower(trim((string) ($line['direction'] ?? '')));
            $amount = (float) $line['amount'];
            $delta += $direction === 'debit' ? $amount : -$amount;
        }
        if (abs($delta) > 0.00001) {
            throw new InvalidArgumentException('Journal entry lines are not balanced (difference ' . $delta . ').');
        }

        $this->pdo->beginTransaction();
        try {
            $entryStmt = $this->pdo->prepare(
                "INSERT INTO accounting.journal_entries (
                    journal_id, entry_date, reference, description, currency, exchange_rate,
                    status, posted_at, metadata
                ) VALUES (
                    :journal_id, :entry_date, :reference, :description, :currency, :exchange_rate,
                    :status, CASE WHEN :status = 'posted' THEN now() ELSE NULL END, :metadata
                )
                RETURNING *"
            );
            $entryStmt->execute([
                ':journal_id' => $journal['id'],
                ':entry_date' => $entryDate,
                ':reference' => $reference,
                ':description' => $description,
                ':currency' => $currency,
                ':exchange_rate' => $exchangeRate,
                ':status' => $status,
                ':metadata' => $metadata,
            ]);
            $entry = $entryStmt->fetch(PDO::FETCH_ASSOC);

            $lineStmt = $this->pdo->prepare(
                "INSERT INTO accounting.journal_entry_lines (
                    entry_id, account_id, rgs_code, description, direction, amount, quantity, metadata
                ) VALUES (
                    :entry_id, :account_id, :rgs_code, :description, :direction, :amount, :quantity, :metadata
                )
                RETURNING *"
            );

            $insertedLines = [];
            foreach ($lines as $line) {
                $prepared = $this->prepareLine($line);
                $lineStmt->execute([
                    ':entry_id' => $entry['id'],
                    ':account_id' => $prepared['account']['id'],
                    ':rgs_code' => $prepared['rgs_code'],
                    ':description' => $prepared['description'],
                    ':direction' => $prepared['direction'],
                    ':amount' => $prepared['amount'],
                    ':quantity' => $prepared['quantity'],
                    ':metadata' => $this->encodeMetadata($prepared['metadata']),
                ]);
                $insertedLines[] = $lineStmt->fetch(PDO::FETCH_ASSOC);
            }

            $this->pdo->commit();
            $entry['lines'] = $insertedLines;
            return $entry;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function postEntry(int $entryId): array {
        $stmt = $this->pdo->prepare(
            "UPDATE accounting.journal_entries
             SET status = 'posted', posted_at = now(), updated_at = now()
             WHERE id = :id
             RETURNING *"
        );
        $stmt->execute([':id' => $entryId]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$entry) {
            throw new InvalidArgumentException('Journal entry not found.');
        }
        $entry['lines'] = $this->getEntryLines($entryId);
        return $entry;
    }

    public function getEntry(int $entryId): ?array {
        $stmt = $this->pdo->prepare('SELECT * FROM accounting.journal_entries WHERE id = :id');
        $stmt->execute([':id' => $entryId]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$entry) {
            return null;
        }
        $entry['lines'] = $this->getEntryLines($entryId);
        return $entry;
    }

    public function getTrialBalance(?string $currency = null): array {
        if ($currency !== null) {
            $stmt = $this->pdo->prepare('SELECT * FROM accounting.trial_balance WHERE currency = :currency ORDER BY account_code');
            $stmt->execute([':currency' => strtoupper($currency)]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $stmt = $this->pdo->query('SELECT * FROM accounting.trial_balance ORDER BY account_code');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function findJournalByCode(string $code): ?array {
        $stmt = $this->pdo->prepare('SELECT * FROM accounting.journals WHERE code = :code');
        $stmt->execute([':code' => $code]);
        $journal = $stmt->fetch(PDO::FETCH_ASSOC);
        return $journal ?: null;
    }

    private function resolveJournal(array $entryData): array {
        if (isset($entryData['journal_id'])) {
            $stmt = $this->pdo->prepare('SELECT * FROM accounting.journals WHERE id = :id');
            $stmt->execute([':id' => (int) $entryData['journal_id']]);
            $journal = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$journal) {
                throw new InvalidArgumentException('Journal not found for given id.');
            }
            return $journal;
        }

        $journalCode = strtoupper(trim((string) ($entryData['journal_code'] ?? '')));
        if ($journalCode === '') {
            throw new InvalidArgumentException('journal_code or journal_id is required.');
        }
        $existing = $this->findJournalByCode($journalCode);
        if ($existing) {
            return $existing;
        }
        $name = $entryData['journal_name'] ?? $journalCode;
        return $this->ensureJournal([
            'code' => $journalCode,
            'name' => $name,
        ]);
    }

    private function prepareLine(array $line): array {
        $direction = strtolower(trim((string) ($line['direction'] ?? '')));
        if (!in_array($direction, ['debit', 'credit'], true)) {
            throw new InvalidArgumentException('Line direction must be debit or credit.');
        }
        if (!isset($line['amount']) || !is_numeric($line['amount']) || $line['amount'] <= 0) {
            throw new InvalidArgumentException('Line amount must be a positive number.');
        }
        $amount = number_format((float) $line['amount'], 5, '.', '');

        $account = $this->resolveAccount($line);
        $rgsCode = $line['rgs_code'] ?? $account['rgs_code'] ?? null;
        if ($rgsCode !== null) {
            $rgsCode = strtoupper(trim((string) $rgsCode));
        }

        $quantity = null;
        if (isset($line['quantity'])) {
            if (!is_numeric($line['quantity'])) {
                throw new InvalidArgumentException('Quantity must be numeric when provided.');
            }
            $quantity = number_format((float) $line['quantity'], 5, '.', '');
        }

        return [
            'account' => $account,
            'rgs_code' => $rgsCode,
            'description' => $line['description'] ?? null,
            'direction' => $direction,
            'amount' => $amount,
            'quantity' => $quantity,
            'metadata' => $line['metadata'] ?? [],
        ];
    }

    private function resolveAccount(array $line): array {
        if (isset($line['account_id'])) {
            $stmt = $this->pdo->prepare('SELECT * FROM accounting.ledger_accounts WHERE id = :id');
            $stmt->execute([':id' => (int) $line['account_id']]);
            $account = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$account) {
                throw new InvalidArgumentException('Account not found for given id.');
            }
            return $account;
        }
        $accountCode = trim((string) ($line['account_code'] ?? ''));
        if ($accountCode === '') {
            throw new InvalidArgumentException('Each line needs account_id or account_code.');
        }
        $stmt = $this->pdo->prepare('SELECT * FROM accounting.ledger_accounts WHERE code = :code');
        $stmt->execute([':code' => $accountCode]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$account) {
            throw new InvalidArgumentException("Account not found for code {$accountCode}.");
        }
        return $account;
    }

    private function getEntryLines(int $entryId): array {
        $stmt = $this->pdo->prepare('SELECT * FROM accounting.journal_entry_lines WHERE entry_id = :entry ORDER BY id');
        $stmt->execute([':entry' => $entryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function validateLines(array $lines): void {
        foreach ($lines as $index => $line) {
            if (!is_array($line)) {
                throw new InvalidArgumentException('Line ' . $index . ' must be an array.');
            }
            if (!isset($line['direction']) || !isset($line['amount'])) {
                throw new InvalidArgumentException('Each line must specify direction and amount.');
            }
        }
    }

    private function encodeMetadata($metadata): string {
        if ($metadata === null) {
            return '{}';
        }
        if (is_string($metadata)) {
            return $metadata === '' ? '{}' : $metadata;
        }
        return json_encode($metadata, JSON_THROW_ON_ERROR);
    }

    private function normaliseAccountType(string $type): string {
        $type = strtolower($type);
        $allowed = ['asset', 'liability', 'equity', 'revenue', 'expense', 'memo'];
        if (!in_array($type, $allowed, true)) {
            return 'memo';
        }
        return $type;
    }
}
