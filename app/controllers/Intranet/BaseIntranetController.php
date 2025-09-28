<?php
namespace App\Controllers\Intranet;

use App\Services\AccountService;
use Core\BaseController;
use Core\Database;
use PDO;

abstract class BaseIntranetController extends BaseController {
    protected ?PDO $pdo = null;
    protected ?array $user = null;
    protected array $roles = [];

    private ?AccountService $accountService = null;
    private bool $activityLogged = false;

    protected function bootSession(): void {
        $this->startSession();
        if ($this->pdo === null) {
            $db = $this->container->get(Database::class);
            $this->pdo = $db->getConnection();
        }

        $this->user = $this->loadUser();
        if ($this->user !== null) {
            $this->roles = $this->accountService()->getRoles($this->user['id']);
            $this->user['roles'] = $this->roles;
            $this->accountService()->updateLastSeen($this->user['id'], $this->currentContext());
        } else {
            $this->roles = [];
        }
    }

    protected function startSession(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_set_cookie_params([
                'httponly' => true,
                'secure' => true,
                'samesite' => 'Strict',
            ]);
            session_start();
        }
    }

    protected function loadUser(): ?array {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        $account = $this->accountService()->getAccountById((int) $_SESSION['user_id']);
        if ($account === null) {
            unset($_SESSION['user_id']);
            return null;
        }
        return $account;
    }

    protected function isAuthenticated(): bool {
        return $this->user !== null;
    }

    protected function hasRole(string $roleKey): bool {
        return in_array($roleKey, $this->roles, true);
    }

    protected function requireAuthenticated(array $request = []): void {
        if (!$this->isAuthenticated()) {
            $this->redirect('/login');
        }
        if (!$this->hasRole('intranet.member')) {
            $this->forbidden();
        }
        $this->logSessionActivity($request);
    }

    protected function ensureAuthenticatedJson(array $request = []): bool {
        if (!$this->isAuthenticated()) {
            $this->json(['error' => 'Unauthorized'], 401);
            return false;
        }
        if (!$this->hasRole('intranet.member')) {
            $this->json(['error' => 'Forbidden'], 403);
            return false;
        }
        $this->logSessionActivity($request);
        return true;
    }

    protected function json($data, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function redirect(string $path): void {
        header('Location: ' . $path);
        exit;
    }

    protected function forbidden(): void {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }

    protected function logAccountEvent(string $eventKind, array $payload = []): void {
        if ($this->user === null) {
            return;
        }
        $this->accountService()->logEvent($this->user['id'], $eventKind, $this->currentContext(), $payload);
    }

    private function logSessionActivity(array $request): void {
        if ($this->activityLogged || $this->user === null) {
            return;
        }

        $payload = [];
        if (!empty($request['route'])) {
            $payload['route'] = $request['route'];
        }
        if (!empty($request['method'])) {
            $payload['method'] = $request['method'];
        }

        $this->logAccountEvent('session.activity', $payload);
        $this->activityLogged = true;
    }

    protected function accountService(): AccountService {
        if ($this->accountService === null) {
            $this->accountService = $this->container->get(AccountService::class);
        }
        return $this->accountService;
    }

    protected function currentContext(): array {
        return [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'route' => $_SERVER['REQUEST_URI'] ?? null,
            'source' => 'intranet',
        ];
    }
}
