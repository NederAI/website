<?php
namespace App\Controllers;

use Core\BaseController;
use Core\Database;
use PDO;

class AuthController extends BaseController {
    private PDO $pdo;

    public function handle($request): bool {
        $db = $this->container->get(Database::class);
        $this->pdo = $db->getConnection();

        $this->delegateRoute('/register!', [$this, 'register'], $request);
        $this->delegateRoute('/login!', [$this, 'login'], $request);
        $this->delegateRoute('/nickname!', [$this, 'changeNickname'], $request);

        return false;
    }

    private function register($request): bool {
        if ($request['method'] !== 'POST') {
            $this->jsonResponse(['error' => 'Method not allowed'], 405);
            return true;
        }
        $data = is_array($request['body']) ? $request['body'] : [];
        if (!isset($data['email'], $data['password'], $data['nickname'])) {
            $this->jsonResponse(['error' => 'Missing fields'], 400);
            return true;
        }
        // check if email already exists
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            $this->jsonResponse(['error' => 'Email already registered'], 409);
            return true;
        }
        $hash = password_hash($data['password'], PASSWORD_DEFAULT);
        $insert = $this->pdo->prepare('INSERT INTO users (email, password_hash, nickname) VALUES (?, ?, ?)');
        $insert->execute([$data['email'], $hash, $data['nickname']]);
        $this->jsonResponse(['success' => true]);
        return true;
    }

    private function login($request): bool {
        if ($request['method'] !== 'POST') {
            $this->jsonResponse(['error' => 'Method not allowed'], 405);
            return true;
        }
        $data = is_array($request['body']) ? $request['body'] : [];
        if (!isset($data['email'], $data['password'])) {
            $this->jsonResponse(['error' => 'Missing fields'], 400);
            return true;
        }
        $stmt = $this->pdo->prepare('SELECT id, password_hash, nickname FROM users WHERE email = ?');
        $stmt->execute([$data['email']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || !password_verify($data['password'], $user['password_hash'])) {
            $this->jsonResponse(['error' => 'Invalid credentials'], 401);
            return true;
        }
        $this->jsonResponse(['success' => true, 'id' => $user['id'], 'nickname' => $user['nickname']]);
        return true;
    }

    private function changeNickname($request): bool {
        if ($request['method'] !== 'POST') {
            $this->jsonResponse(['error' => 'Method not allowed'], 405);
            return true;
        }
        $data = is_array($request['body']) ? $request['body'] : [];
        if (!isset($data['email'], $data['nickname'])) {
            $this->jsonResponse(['error' => 'Missing fields'], 400);
            return true;
        }
        $stmt = $this->pdo->prepare('UPDATE users SET nickname = ? WHERE email = ?');
        $stmt->execute([$data['nickname'], $data['email']]);
        if ($stmt->rowCount() === 0) {
            $this->jsonResponse(['error' => 'User not found'], 404);
            return true;
        }
        $this->jsonResponse(['success' => true]);
        return true;
    }

    private function jsonResponse(array $data, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
