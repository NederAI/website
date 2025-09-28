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

    public function listOrganizations(): array {
        $stmt = $this->pdo->query(
            "SELECT id, code, name, parent_id, path::text AS path, currency, metadata, created_at, updated_at
             FROM accounting.organizations
             ORDER BY path"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['parent_id'] = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
            $row['metadata'] = $this->decodeMetadata($row['metadata'] ?? null);
        }
        return $rows;
    }

    public function listOrganizationTree(): array {
        $items = $this->listOrganizations();
        $byId = [];
        foreach ($items as $org) {
            $org['children'] = [];
            $byId[$org['id']] = $org;
        }
        $tree = [];
        foreach ($byId as $id => &$org) {
            if ($org['parent_id'] !== null && isset($byId[$org['parent_id']])) {
                $byId[$org['parent_id']]['children'][] =& $org;
            } else {
                $tree[] =& $org;
            }
        }
        unset($org);
        return array_map(fn($node) => $this->cloneTree($node), $tree);
    }

    private function cloneTree(array $node): array {
        $children = [];
        foreach ($node['children'] as $child) {
            $children[] = $this->cloneTree($child);
        }
        $node['children'] = $children;
        return $node;
    }

    public function getOrganizationByCode(string $code): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT id, code, name, parent_id, path::text AS path, currency, metadata, created_at, updated_at
             FROM accounting.organizations WHERE upper(code) = :code'
        );
        $stmt->execute([':code' => strtoupper(trim($code))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row['id'] = (int) $row['id'];
        $row['parent_id'] = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
        $row['metadata'] = $this->decodeMetadata($row['metadata'] ?? null);
        return $row;
    }

    public function getOrganizationById(int $id): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT id, code, name, parent_id, path::text AS path, currency, metadata, created_at, updated_at
             FROM accounting.organizations WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row['id'] = (int) $row['id'];
        $row['parent_id'] = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
        $row['metadata'] = $this->decodeMetadata($row['metadata'] ?? null);
        return $row;
    }

    public function getOrganizationCurrency(int $orgId): string {
        $stmt = $this->pdo->prepare('SELECT currency FROM accounting.organizations WHERE id = :id');
        $stmt->execute([':id' => $orgId]);
        $currency = $stmt->fetchColumn();
        if (!$currency) {
            throw new InvalidArgumentException('Organization not found.');
        }
        return strtoupper(trim($currency));
    }

    public function listAccounts(int $orgId): array {
        $stmt = $this->pdo->prepare(
            'SELECT id, org_id, code, name, type, rgs_code, currency, metadata, archived_at, created_at, updated_at
             FROM accounting.accounts
             WHERE org_id = :org
             ORDER BY code'
        );
        $stmt->execute([':org' => $orgId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['org_id'] = (int) $row['org_id'];
            $row['metadata'] = $this->decodeMetadata($row['metadata'] ?? null);
        }
        return $rows;
    }

    public function createAccount(int $orgId, array $payload): array {
        $code = strtoupper(trim((string) ($payload['code'] ?? '')));
        if ($code === '') {
            throw new InvalidArgumentException('Account code is verplicht.');
        }

        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Account naam is verplicht.');
        }

        $typeInput = $payload['type'] ?? $payload['account_type'] ?? null;
        if ($typeInput === null || trim((string) $typeInput) === '') {
            throw new InvalidArgumentException('Account type is verplicht.');
        }
        $type = $this->normaliseAccountType($typeInput);
        if ($type === 'memo') {
            throw new InvalidArgumentException('Account type moet asset/liability/equity/revenue/expense.');
        }

        $rgsCodeRaw = $payload['rgs_code'] ?? null;
        $rgsCode = $rgsCodeRaw !== null ? strtoupper(trim((string) $rgsCodeRaw)) : null;
        if ($rgsCode === '') {
            $rgsCode = null;
        }
        if ($rgsCode !== null && !$this->rgs->getByCode($rgsCode)) {
            throw new InvalidArgumentException("Onbekende RGS-code: {$rgsCode}.");
        }

        $currency = strtoupper(trim((string) ($payload['currency'] ?? $this->getOrganizationCurrency($orgId))));
        if ($currency === '') {
            $currency = $this->getOrganizationCurrency($orgId);
        }

        $metadata = $this->encodeMetadata($payload['metadata'] ?? []);

        $stmt = $this->pdo->prepare(
            "INSERT INTO accounting.accounts (org_id, code, name, type, rgs_code, currency, metadata)
             VALUES (:org, :code, :name, :type, :rgs, :currency, :metadata)
             ON CONFLICT (org_id, code) DO UPDATE SET
                name = EXCLUDED.name,
                type = EXCLUDED.type,
                rgs_code = EXCLUDED.rgs_code,
                currency = EXCLUDED.currency,
                metadata = EXCLUDED.metadata,
                updated_at = now()
             RETURNING *"
        );
        $stmt->execute([
            ':org' => $orgId,
            ':code' => $code,
            ':name' => $name,
            ':type' => $type,
            ':rgs' => $rgsCode,
            ':currency' => $currency,
            ':metadata' => $metadata,
        ]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        $account['id'] = (int) $account['id'];
        $account['org_id'] = (int) $account['org_id'];
        $account['metadata'] = $this->decodeMetadata($account['metadata'] ?? null);
        return $account;
    }

    public function listEntries(int $orgId, int $limit = 25): array {
        $stmt = $this->pdo->prepare(
            "SELECT e.id, e.org_id, e.entry_date, e.status, e.reference, e.description, e.currency, e.exchange_rate,
                    e.intercompany_id, e.metadata,
                    COALESCE(SUM(CASE WHEN l.direction = 'debit' THEN l.amount ELSE 0 END), 0) AS total_debit,
                    COALESCE(SUM(CASE WHEN l.direction = 'credit' THEN l.amount ELSE 0 END), 0) AS total_credit
             FROM accounting.entries e
             LEFT JOIN accounting.lines l ON l.entry_id = e.id AND l.node = 'line'
             WHERE e.org_id = :org
             GROUP BY e.id
             ORDER BY e.entry_date DESC, e.id DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':org', $orgId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['org_id'] = (int) $row['org_id'];
            $row['total_debit'] = (float) $row['total_debit'];
            $row['total_credit'] = (float) $row['total_credit'];
            $row['balance'] = $row['total_debit'] - $row['total_credit'];
            $row['metadata'] = $this->decodeMetadata($row['metadata'] ?? null);
        }
        return $rows;
    }

    public function getTrialBalance(int $orgId): array {
        $stmt = $this->pdo->prepare(
            "SELECT a.id AS account_id, a.code, a.name, a.type, a.currency,
                    COALESCE(SUM(CASE WHEN l.direction = 'debit' THEN l.amount ELSE 0 END), 0) AS total_debit,
                    COALESCE(SUM(CASE WHEN l.direction = 'credit' THEN l.amount ELSE 0 END), 0) AS total_credit
             FROM accounting.accounts a
             LEFT JOIN accounting.lines l ON l.account_id = a.id AND l.node = 'line'
             WHERE a.org_id = :org
             GROUP BY a.id, a.code, a.name, a.type, a.currency
             ORDER BY a.code"
        );
        $stmt->execute([':org' => $orgId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['account_id'] = (int) $row['account_id'];
            $row['total_debit'] = (float) $row['total_debit'];
            $row['total_credit'] = (float) $row['total_credit'];
            $row['balance'] = $row['total_debit'] - $row['total_credit'];
        }
        return $rows;
    }

    public function getEntry(int $entryId): ?array {
        $stmt = $this->pdo->prepare('SELECT * FROM accounting.entries WHERE id = :id');
        $stmt->execute([':id' => $entryId]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$entry) {
            return null;
        }
        $entry['id'] = (int) $entry['id'];
        $entry['org_id'] = (int) $entry['org_id'];
        $entry['metadata'] = $this->decodeMetadata($entry['metadata'] ?? null);
        $entry['lines'] = $this->getEntryLines($entryId);
        return $entry;
    }

    public function createEntry(int $orgId, array $entryData, array $lines): array {
        if (count($lines) < 2) {
            throw new InvalidArgumentException('Een boeking moet minimaal twee regels bevatten.');
        }

        $org = $this->getOrganizationById($orgId);
        if (!$org) {
            throw new InvalidArgumentException('Onbekende organisatie.');
        }

        $entryDate = $entryData['entry_date'] ?? date('Y-m-d');
        if (!$this->isValidDate($entryDate)) {
            throw new InvalidArgumentException('Ongeldige boekingsdatum.');
        }

        $status = strtolower(trim((string) ($entryData['status'] ?? 'draft')));
        if (!in_array($status, ['draft', 'posted', 'void'], true)) {
            throw new InvalidArgumentException('Status moet draft, posted of void zijn.');
        }

        $currency = strtoupper(trim((string) ($entryData['currency'] ?? $org['currency'])));
        if ($currency === '') {
            $currency = $org['currency'];
        }

        $exchangeRate = (float) ($entryData['exchange_rate'] ?? 1.0);
        if ($exchangeRate <= 0) {
            throw new InvalidArgumentException('Wisselkoers moet positief zijn.');
        }

        $reference = trim((string) ($entryData['reference'] ?? ''));
        $description = trim((string) ($entryData['description'] ?? ''));
        $intercompany = $entryData['intercompany_id'] ?? null;
        $metadata = $this->encodeMetadata($entryData['metadata'] ?? []);

        $preparedLines = [];
        $delta = 0.0;
        foreach ($lines as $index => $line) {
            if (!is_array($line)) {
                throw new InvalidArgumentException('Boekingsregel ' . ($index + 1) . ' is ongeldig.');
            }
            $direction = strtolower(trim((string) ($line['direction'] ?? '')));
            if (!in_array($direction, ['debit', 'credit'], true)) {
                throw new InvalidArgumentException('Boekingsregel ' . ($index + 1) . ' heeft een ongeldige richting.');
            }
            if (!isset($line['amount']) || !is_numeric($line['amount'])) {
                throw new InvalidArgumentException('Boekingsregel ' . ($index + 1) . ' mist een bedrag.');
            }
            $amount = (float) $line['amount'];
            if ($amount <= 0) {
                throw new InvalidArgumentException('Boekingsregel ' . ($index + 1) . ' moet een positief bedrag hebben.');
            }

            $account = $this->resolveAccount($orgId, $line);
            $preparedLines[] = [
                'account_id' => $account['id'],
                'direction' => $direction,
                'amount' => $amount,
                'description' => trim((string) ($line['description'] ?? '')),
                'metadata' => $this->encodeMetadata($line['metadata'] ?? []),
            ];
            $delta += $direction === 'debit' ? $amount : -$amount;
        }

        if (abs($delta) > 0.00001) {
            throw new InvalidArgumentException('Boeking is niet in balans. Verschil: ' . $delta);
        }

        $this->pdo->beginTransaction();
        try {
            $entryStmt = $this->pdo->prepare(
                "INSERT INTO accounting.entries (
                    org_id, entry_date, status, reference, description, currency,
                    exchange_rate, intercompany_id, metadata, posted_at
                ) VALUES (
                    :org, :entry_date, :status, :reference, :description, :currency,
                    :exchange_rate, :intercompany_id, :metadata,
                    CASE WHEN :status = 'posted' THEN now() ELSE NULL END
                ) RETURNING *"
            );
            $entryStmt->execute([
                ':org' => $orgId,
                ':entry_date' => $entryDate,
                ':status' => $status,
                ':reference' => $reference !== '' ? $reference : null,
                ':description' => $description !== '' ? $description : null,
                ':currency' => $currency,
                ':exchange_rate' => $exchangeRate,
                ':intercompany_id' => $intercompany,
                ':metadata' => $metadata,
            ]);
            $entry = $entryStmt->fetch(PDO::FETCH_ASSOC);

            $lineStmt = $this->pdo->prepare(
                "INSERT INTO accounting.lines (
                    entry_id, org_id, node, path, account_id, direction, amount, metadata, description, require_balanced
                ) VALUES (
                    :entry_id, :org_id, :node, :path, :account_id, :direction, :amount, :metadata, :description, :require_balanced
                )
                RETURNING *"
            );

            $rootDescription = $description !== '' ? $description : 'Batch';
            $lineStmt->execute([
                ':entry_id' => $entry['id'],
                ':org_id' => $orgId,
                ':node' => 'group',
                ':path' => 'root.0001',
                ':account_id' => null,
                ':direction' => null,
                ':amount' => null,
                ':metadata' => '{}',
                ':description' => $rootDescription,
                ':require_balanced' => 't',
            ]);

            foreach ($preparedLines as $index => $line) {
                $lineStmt->execute([
                    ':entry_id' => $entry['id'],
                    ':org_id' => $orgId,
                    ':node' => 'line',
                    ':path' => sprintf('root.0001.%04d', $index + 1),
                    ':account_id' => $line['account_id'],
                    ':direction' => $line['direction'],
                    ':amount' => number_format($line['amount'], 5, '.', ''),
                    ':metadata' => $line['metadata'],
                    ':description' => $line['description'] !== '' ? $line['description'] : null,
                    ':require_balanced' => null,
                ]);
            }

            $this->pdo->commit();
            return $this->getEntry((int) $entry['id']);
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function getEntryLines(int $entryId): array {
        $stmt = $this->pdo->prepare(
            'SELECT id, entry_id, org_id, node, path, account_id, direction, amount, metadata, description, require_balanced
             FROM accounting.lines
             WHERE entry_id = :entry
             ORDER BY path'
        );
        $stmt->execute([':entry' => $entryId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['entry_id'] = (int) $row['entry_id'];
            $row['org_id'] = (int) $row['org_id'];
            $row['account_id'] = $row['account_id'] !== null ? (int) $row['account_id'] : null;
            $row['amount'] = $row['amount'] !== null ? (float) $row['amount'] : null;
            $row['require_balanced'] = in_array($row['require_balanced'], ['t', 'true', 1, true], true);
            $row['metadata'] = $this->decodeMetadata($row['metadata'] ?? null);
        }
        return $rows;
    }

    private function resolveAccount(int $orgId, array $line): array {
        if (isset($line['account_id'])) {
            $stmt = $this->pdo->prepare('SELECT * FROM accounting.accounts WHERE id = :id AND org_id = :org');
            $stmt->execute([':id' => (int) $line['account_id'], ':org' => $orgId]);
            $account = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$account) {
                throw new InvalidArgumentException('Onbekende grootboekrekening-id voor deze organisatie.');
            }
            $account['id'] = (int) $account['id'];
            return $account;
        }

        $code = strtoupper(trim((string) ($line['account_code'] ?? '')));
        if ($code === '') {
            throw new InvalidArgumentException('Elke regel heeft account_id of account_code nodig.');
        }
        $stmt = $this->pdo->prepare('SELECT * FROM accounting.accounts WHERE org_id = :org AND code = :code');
        $stmt->execute([':org' => $orgId, ':code' => $code]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$account) {
            throw new InvalidArgumentException("Onbekende grootboekrekening {$code} voor deze organisatie.");
        }
        $account['id'] = (int) $account['id'];
        return $account;
    }

    private function normaliseAccountType($value): string {
        $type = strtolower(trim((string) $value));
        $allowed = ['asset', 'liability', 'equity', 'revenue', 'expense', 'memo'];
        if (in_array($type, $allowed, true)) {
            return $type;
        }
        return 'memo';
    }

    private function isValidDate(string $value): bool {
        $dt = date_create($value);
        return $dt !== false && $dt->format('Y-m-d') === $value;
    }

    private function encodeMetadata($value): string {
        if ($value === null) {
            return '{}';
        }
        if (is_string($value) && $value !== '') {
            return $value;
        }
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        return '{}';
    }

    private function decodeMetadata($value) {
        if ($value === null || $value === '' || $value === '{}') {
            return [];
        }
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string) $value, true);
        return $decoded ?? [];
    }
}
