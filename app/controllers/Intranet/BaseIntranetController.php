<?php
namespace App\Controllers\Intranet;

use Core\BaseController;
use Core\Database;
use PDO;

abstract class BaseIntranetController extends BaseController {
    protected ?PDO $pdo = null;
    protected ?array $user = null;

    protected function bootSession(): void {
        $this->startSession();
        if ($this->pdo === null) {
            $db = $this->container->get(Database::class);
            $this->pdo = $db->getConnection();
        }
        $this->user = $this->loadUser();
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
        if ($this->pdo === null) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT id, email, nickname, created_at FROM users WHERE id = :id');
        $stmt->execute([':id' => (int) $_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            unset($_SESSION['user_id']);
            return null;
        }
        return $user;
    }

    protected function isAuthenticated(): bool {
        return $this->user !== null;
    }

    protected function requireAuthenticated(): void {
        if ($this->isAuthenticated()) {
            return;
        }
        $this->redirect('/login');
    }

    protected function ensureAuthenticatedJson(): bool {
        if ($this->isAuthenticated()) {
            return true;
        }
        $this->json(['error' => 'Unauthorized'], 401);
        return false;
    }

    protected function json($data, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    protected function redirect(string $path): void {
        header('Location: ' . $path);
        exit;
    }
}