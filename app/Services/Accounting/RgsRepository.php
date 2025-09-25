<?php
namespace App\Services\Accounting;

use Core\Container;
use Core\Database;
use InvalidArgumentException;
use PDO;
use SplFileObject;

class RgsRepository {
    private PDO $pdo;

    public function __construct(Container $container) {
        $this->pdo = $container->get(Database::class)->getConnection();
    }

    public function getByCode(string $code): ?array {
        $stmt = $this->pdo->prepare('SELECT * FROM accounting.rgs_nodes WHERE code = :code');
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function search(string $term, int $limit = 25): array {
        $stmt = $this->pdo->prepare('SELECT * FROM accounting.rgs_nodes WHERE code ILIKE :term OR title_nl ILIKE :term ORDER BY code LIMIT :limit');
        $stmt->bindValue(':term', '%' . $term . '%');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function importFromCsv(string $path, array $options = []): array {
        if (!is_readable($path)) {
            throw new InvalidArgumentException("CSV file not readable: {$path}");
        }

        $delimiter = $options['delimiter'] ?? ';';
        $versionTag = $options['version_tag'] ?? null;

        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl($delimiter);

        if ($file->eof()) {
            throw new InvalidArgumentException('CSV file is empty.');
        }

        $headerRow = $file->fgetcsv();
        if (!is_array($headerRow)) {
            throw new InvalidArgumentException('Unable to read CSV header row.');
        }

        $headerMap = $this->normaliseHeaders($headerRow);

        $required = ['code', 'description'];
        foreach ($required as $needle) {
            if (!isset($headerMap[$needle])) {
                throw new InvalidArgumentException("CSV file is missing a column for '{$needle}'.");
            }
        }

        $inserted = 0;
        $updated = 0;

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO accounting.rgs_nodes (
                    code, title_nl, title_en, level, parent_code, account_type,
                    function_label, is_postable, version_tag
                ) VALUES (
                    :code, :title_nl, :title_en, :level, :parent_code, :account_type,
                    :function_label, :is_postable, :version_tag
                )
                ON CONFLICT (code) DO UPDATE SET
                    title_nl = EXCLUDED.title_nl,
                    title_en = EXCLUDED.title_en,
                    level = EXCLUDED.level,
                    parent_code = EXCLUDED.parent_code,
                    account_type = EXCLUDED.account_type,
                    function_label = EXCLUDED.function_label,
                    is_postable = EXCLUDED.is_postable,
                    version_tag = EXCLUDED.version_tag,
                    updated_at = now()
                RETURNING (xmax = 0) AS inserted'
            );

            foreach ($file as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $record = $this->mapCsvRow($row, $headerMap, $versionTag);
                if ($record === null) {
                    continue;
                }
                $stmt->execute($record);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result && $result['inserted'] === 't') {
                    $inserted++;
                } else {
                    $updated++;
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return ['inserted' => $inserted, 'updated' => $updated];
    }

    private function normaliseHeaders(array $headerRow): array {
        $map = [];
        foreach ($headerRow as $index => $value) {
            if ($value === null) {
                continue;
            }
            $normalised = strtolower(trim((string) $value));
            switch ($normalised) {
                case 'rgs-code':
                case 'code':
                case 'rgs code':
                    $map['code'] = $index;
                    break;
                case 'omschrijving':
                case 'naam':
                case 'description':
                    $map['description'] = $index;
                    break;
                case 'description en':
                case 'omschrijving en':
                case 'name en':
                    $map['description_en'] = $index;
                    break;
                case 'niveau':
                case 'level':
                    $map['level'] = $index;
                    break;
                case 'ouder':
                case 'parent':
                case 'parent code':
                case 'rgs-code-ouder':
                    $map['parent_code'] = $index;
                    break;
                case 'balansmutatiesoort':
                case 'balansmutatie':
                case 'type':
                case 'categorie':
                case 'soort grootboekrekening':
                    $map['account_type'] = $index;
                    break;
                case 'functie':
                case 'function':
                    $map['function'] = $index;
                    break;
                case 'mutatiesoort':
                case 'boekbaar':
                case 'is leaf':
                case 'is_leaf':
                    $map['is_postable'] = $index;
                    break;
            }
        }
        return $map;
    }

    private function mapCsvRow(array $row, array $headerMap, ?string $versionTag): ?array {
        $rawCode = $row[$headerMap['code']] ?? null;
        $code = trim((string) $rawCode);
        if ($code === '') {
            return null;
        }

        $description = trim((string) ($row[$headerMap['description']] ?? ''));
        if ($description === '') {
            return null;
        }

        $descriptionEn = null;
        if (isset($headerMap['description_en'])) {
            $descriptionEn = trim((string) ($row[$headerMap['description_en']] ?? '')) ?: null;
        }

        $level = null;
        if (isset($headerMap['level'])) {
            $levelRaw = trim((string) ($row[$headerMap['level']] ?? ''));
            $level = ctype_digit($levelRaw) ? (int) $levelRaw : null;
        }
        if ($level === null) {
            $level = max(1, substr_count($code, '.') + 1);
        }

        $parentCode = null;
        if (isset($headerMap['parent_code'])) {
            $parentCode = trim((string) ($row[$headerMap['parent_code']] ?? '')) ?: null;
        }
        if ($parentCode === '' || $parentCode === $code) {
            $parentCode = null;
        }

        $accountType = 'memo';
        if (isset($headerMap['account_type'])) {
            $accountType = $this->normaliseAccountType((string) ($row[$headerMap['account_type']] ?? ''));
        }
        if ($accountType === 'memo') {
            $accountType = $this->inferAccountTypeFromCode($code);
        }

        $functionLabel = null;
        if (isset($headerMap['function'])) {
            $functionLabel = trim((string) ($row[$headerMap['function']] ?? '')) ?: null;
        }

        $isPostable = false;
        if (isset($headerMap['is_postable'])) {
            $isPostable = $this->normaliseBoolean($row[$headerMap['is_postable']]);
        } else {
            $isPostable = substr_count($code, '.') >= 2;
        }

        return [
            ':code' => $code,
            ':title_nl' => $description,
            ':title_en' => $descriptionEn,
            ':level' => $level,
            ':parent_code' => $parentCode,
            ':account_type' => $accountType,
            ':function_label' => $functionLabel,
            ':is_postable' => $isPostable ? 't' : 'f',
            ':version_tag' => $versionTag,
        ];
    }

    private function normaliseAccountType(string $value): string {
        $normalised = strtolower(trim($value));
        if ($normalised === '') {
            return 'memo';
        }
        if (str_contains($normalised, 'activa') || str_contains($normalised, 'asset')) {
            return 'asset';
        }
        if (str_contains($normalised, 'passiva') || str_contains($normalised, 'liabil')) {
            return 'liability';
        }
        if (str_contains($normalised, 'vermogen') || str_contains($normalised, 'equity')) {
            return 'equity';
        }
        if (str_contains($normalised, 'opbrengst') || str_contains($normalised, 'revenue') || str_contains($normalised, 'omzet')) {
            return 'revenue';
        }
        if (str_contains($normalised, 'kosten') || str_contains($normalised, 'expense')) {
            return 'expense';
        }
        if (str_contains($normalised, 'balans')) {
            return 'asset';
        }
        if (str_contains($normalised, 'result')) {
            return 'expense';
        }
        return 'memo';
    }

    private function inferAccountTypeFromCode(string $code): string {
        $prefix = strtoupper(substr($code, 0, 1));
        return $prefix === 'B' ? 'asset' : ($prefix === 'W' ? 'expense' : 'memo');
    }

    private function normaliseBoolean($value): bool {
        if (is_bool($value)) {
            return $value;
        }
        $string = strtolower(trim((string) $value));
        return in_array($string, ['1','true','ja','yes','y'], true);
    }
}

