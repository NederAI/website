<?php
namespace App\Services;

use Core\Container;
use Core\Database;
use PDO;

class AccountService {
    private PDO $pdo;

    public function __construct(Container $container) {
        $db = $container->get(Database::class);
        $this->pdo = $db->getConnection();
    }

    public function register(string $email, string $password, string $nickname, array $context = []): array {
        $email = strtolower(trim($email));
        $nickname = trim($nickname);
        if ($nickname === '') {
            return ['success' => false, 'code' => 'invalid_nickname'];
        }

        if ($this->findAccountByEmail($email, true) !== null) {
            return ['success' => false, 'code' => 'duplicate'];
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->pdo->prepare(
            'INSERT INTO identity.accounts (email, password_hash, nickname, status, needs_password_reset, last_seen_at)
             VALUES (:email, :password_hash, :nickname, :status, :needs_reset, NOW())'
        );
        $stmt->execute([
            ':email' => $email,
            ':password_hash' => $hash,
            ':nickname' => $nickname,
            ':status' => 'active',
            ':needs_reset' => false,
        ]);

        $accountId = (int) $this->pdo->lastInsertId();

        $this->assignRole($accountId, 'intranet.member', null, 'auto-grant on registration');
        $this->logEvent($accountId, 'account.registered', $context, ['nickname' => $nickname]);

        $account = $this->getAccountById($accountId);
        return ['success' => true, 'account' => $account];
    }

    public function validateCredentials(string $email, string $password, array $context = []): array {
        $email = strtolower(trim($email));
        $account = $this->findAccountByEmail($email, true);
        if ($account === null) {
            return ['success' => false, 'code' => 'not_found'];
        }

        if (!password_verify($password, $account['password_hash'])) {
            $this->logEvent((int) $account['account_id'], 'login.failure', $context, ['reason' => 'invalid_password']);
            return ['success' => false, 'code' => 'invalid_password'];
        }

        if ($account['status'] !== 'active') {
            $this->logEvent((int) $account['account_id'], 'login.blocked', $context, ['status' => $account['status']]);
            return ['success' => false, 'code' => $account['status']];
        }

        return ['success' => true, 'account' => $this->sanitizeAccount($account)];
    }

    public function completeLogin(int $accountId, array $context = []): array {
        $stmt = $this->pdo->prepare(
            'UPDATE identity.accounts
             SET last_authenticated_at = NOW(), last_seen_at = NOW(), needs_password_reset = FALSE
             WHERE account_id = :account_id'
        );
        $stmt->execute([':account_id' => $accountId]);

        $this->logEvent($accountId, 'login.success', $context);

        return $this->getAccountById($accountId) ?? ['id' => $accountId];
    }

    public function changeNickname(int $accountId, string $nickname, ?int $actorAccountId, array $context = []): array {
        $nickname = trim($nickname);
        if ($nickname === '') {
            return ['success' => false, 'code' => 'invalid_nickname'];
        }

        $stmt = $this->pdo->prepare(
            'UPDATE identity.accounts SET nickname = :nickname, updated_at = NOW() WHERE account_id = :account_id'
        );
        $stmt->execute([
            ':nickname' => $nickname,
            ':account_id' => $accountId,
        ]);

        $payload = ['nickname' => $nickname];
        if ($actorAccountId !== null) {
            $payload['actor_id'] = $actorAccountId;
        }
        $this->logEvent($accountId, 'profile.nickname_changed', $context, $payload);

        $account = $this->getAccountById($accountId);
        return ['success' => $account !== null, 'account' => $account];
    }

    public function updateLastSeen(int $accountId, array $context = []): void {
        $stmt = $this->pdo->prepare(
            'UPDATE identity.accounts SET last_seen_at = NOW() WHERE account_id = :account_id'
        );
        $stmt->execute([':account_id' => $accountId]);
        $this->logEvent($accountId, 'session.seen', $context, [], false);
    }

    public function getAccountById(int $accountId): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT account_id, email, nickname, status, needs_password_reset, last_authenticated_at, last_seen_at, created_at
             FROM identity.accounts
             WHERE account_id = :account_id AND deleted_at IS NULL'
        );
        $stmt->execute([':account_id' => $accountId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return $this->finalizeAccountRow($row);
    }

    public function findAccountByEmail(string $email, bool $includeSensitive = false): ?array {
        $email = strtolower(trim($email));
        $columns = 'account_id, email, nickname, status, needs_password_reset, last_authenticated_at, last_seen_at, created_at';
        if ($includeSensitive) {
            $columns .= ', password_hash';
        }
        $stmt = $this->pdo->prepare(
            "SELECT $columns FROM identity.accounts WHERE email = :email AND deleted_at IS NULL"
        );
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        if ($includeSensitive) {
            return $row;
        }
        return $this->finalizeAccountRow($row);
    }

    public function getRoles(int $accountId): array {
        $stmt = $this->pdo->prepare(
            'SELECT r.role_key FROM identity.account_roles ar
             JOIN identity.roles r ON r.role_id = ar.role_id
             WHERE ar.account_id = :account_id'
        );
        $stmt->execute([':account_id' => $accountId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    public function assignRole(int $accountId, string $roleKey, ?int $grantedBy = null, ?string $reason = null): void {
        $roleId = $this->ensureRole($roleKey);
        $stmt = $this->pdo->prepare(
            'INSERT INTO identity.account_roles (account_id, role_id, granted_by, grant_reason)
             VALUES (:account_id, :role_id, :granted_by, :reason)
             ON CONFLICT (account_id, role_id) DO UPDATE SET grant_reason = EXCLUDED.grant_reason'
        );
        $stmt->execute([
            ':account_id' => $accountId,
            ':role_id' => $roleId,
            ':granted_by' => $grantedBy,
            ':reason' => $reason,
        ]);
    }

    public function hasRole(int $accountId, string $roleKey): bool {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM identity.account_roles ar
             JOIN identity.roles r ON r.role_id = ar.role_id
             WHERE ar.account_id = :account_id AND r.role_key = :role_key'
        );
        $stmt->execute([
            ':account_id' => $accountId,
            ':role_key' => $roleKey,
        ]);
        return (bool) $stmt->fetchColumn();
    }

    public function logEvent(int $accountId, string $eventKind, array $context = [], array $payload = [], bool $record = true): void {
        if (!$record) {
            return;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO identity.account_events (account_id, event_kind, ip_address, user_agent, payload)
             VALUES (:account_id, :event_kind, :ip_address, :user_agent, :payload)'
        );
        $stmt->execute([
            ':account_id' => $accountId,
            ':event_kind' => $eventKind,
            ':ip_address' => $context['ip'] ?? null,
            ':user_agent' => $context['user_agent'] ?? null,
            ':payload' => $this->encodePayload($payload),
        ]);
    }

    private function ensureRole(string $roleKey): int {
        $stmt = $this->pdo->prepare('SELECT role_id FROM identity.roles WHERE role_key = :role_key');
        $stmt->execute([':role_key' => $roleKey]);
        $roleId = $stmt->fetchColumn();
        if ($roleId !== false) {
            return (int) $roleId;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO identity.roles (role_key, name, description) VALUES (:role_key, :name, :description)'
        );
        $label = ucwords(str_replace(['.', '_'], ' ', $roleKey));
        $insert->execute([
            ':role_key' => $roleKey,
            ':name' => $label,
            ':description' => 'Auto-created role',
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    private function sanitizeAccount(array $raw): array {
        unset($raw['password_hash']);
        return $this->finalizeAccountRow($raw);
    }

    private function finalizeAccountRow(array $row): array {
        $account = [
            'id' => (int) $row['account_id'],
            'email' => $row['email'],
            'nickname' => $row['nickname'],
            'status' => $row['status'],
            'needs_password_reset' => (bool) $row['needs_password_reset'],
            'last_authenticated_at' => $row['last_authenticated_at'] ?? null,
            'last_seen_at' => $row['last_seen_at'] ?? null,
            'created_at' => $row['created_at'] ?? null,
        ];
        $account['roles'] = $this->getRoles($account['id']);
        return $account;
    }

    private function encodePayload(array $payload): string {
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $encoded === false ? '{}' : $encoded;
    }
}
