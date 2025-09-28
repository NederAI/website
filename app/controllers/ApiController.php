<?php
namespace App\Controllers;

use Core\BaseController;
use Core\Database;
use App\Controllers\AuthController;
use PDO;

class ApiController extends BaseController {
    public function handle($request): bool {
        $this->delegateRoute('/auth', AuthController::class, $request);
        $this->delegateRoute('/intranet/snapshot!', [$this, 'snapshot'], $request);
        return false;
    }

    private function ensureIntranetSession(): bool {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_set_cookie_params([
                'httponly' => true,
                'secure' => true,
                'samesite' => 'Strict',
            ]);
            session_start();
        }
        return isset($_SESSION['user_id']);
    }

    private function json($data, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
