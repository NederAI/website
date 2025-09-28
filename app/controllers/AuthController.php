<?php
namespace App\Controllers;

use App\Services\AccountService;
use Core\BaseController;

class AuthController extends BaseController {
    private AccountService $accounts;

    public function handle($request): bool {
        $this->accounts = $this->container->get(AccountService::class);

        $this->delegateRoute('/register!', [$this, 'register'], $request);
        $this->delegateRoute('/login!', [$this, 'login'], $request);
        $this->delegateRoute('/nickname!', [$this, 'changeNickname'], $request);

        return false;
    }

    private function register($request): bool {
        if (($request['method'] ?? 'GET') !== 'POST') {
            $this->jsonResponse(['error' => 'Method not allowed'], 405);
            return true;
        }
        $data = is_array($request['body']) ? $request['body'] : [];
        $email = isset($data['email']) ? trim((string) $data['email']) : '';
        $password = isset($data['password']) ? (string) $data['password'] : '';
        $nickname = isset($data['nickname']) ? trim((string) $data['nickname']) : '';

        if ($email === '' || $password === '' || $nickname === '') {
            $this->jsonResponse(['error' => 'Missing fields'], 400);
            return true;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->jsonResponse(['error' => 'Invalid email address'], 422);
            return true;
        }

        $context = $this->requestContext($request);
        $result = $this->accounts->register($email, $password, $nickname, $context);
        if (!$result['success']) {
            $code = $result['code'] ?? 'error';
            if ($code === 'duplicate') {
                $this->jsonResponse(['error' => 'Email already registered'], 409);
            } elseif ($code === 'invalid_nickname') {
                $this->jsonResponse(['error' => 'Nickname required'], 422);
            } else {
                $this->jsonResponse(['error' => 'Registration failed'], 400);
            }
            return true;
        }

        $account = $result['account'];
        $this->jsonResponse([
            'success' => true,
            'account' => [
                'id' => $account['id'],
                'email' => $account['email'],
                'nickname' => $account['nickname'],
                'roles' => $account['roles'],
            ],
        ]);
        return true;
    }

    private function login($request): bool {
        if (($request['method'] ?? 'GET') !== 'POST') {
            $this->jsonResponse(['error' => 'Method not allowed'], 405);
            return true;
        }
        $data = is_array($request['body']) ? $request['body'] : [];
        $email = isset($data['email']) ? trim((string) $data['email']) : '';
        $password = isset($data['password']) ? (string) $data['password'] : '';

        if ($email === '' || $password === '') {
            $this->jsonResponse(['error' => 'Missing fields'], 400);
            return true;
        }

        $context = $this->requestContext($request);
        $verification = $this->accounts->validateCredentials($email, $password, $context);
        if (!$verification['success']) {
            $code = $verification['code'] ?? 'invalid';
            if (in_array($code, ['invalid_password', 'not_found'], true)) {
                $this->jsonResponse(['error' => 'Invalid credentials'], 401);
            } else {
                $this->jsonResponse(['error' => 'Account not available', 'code' => $code], 403);
            }
            return true;
        }

        $account = $this->accounts->completeLogin($verification['account']['id'], $context);
        if (!in_array('intranet.member', $account['roles'], true)) {
            $this->jsonResponse(['error' => 'Access denied'], 403);
            return true;
        }

        $this->jsonResponse([
            'success' => true,
            'account' => [
                'id' => $account['id'],
                'email' => $account['email'],
                'nickname' => $account['nickname'],
                'roles' => $account['roles'],
                'last_authenticated_at' => $account['last_authenticated_at'],
            ],
        ]);
        return true;
    }

    private function changeNickname($request): bool {
        if (($request['method'] ?? 'GET') !== 'POST') {
            $this->jsonResponse(['error' => 'Method not allowed'], 405);
            return true;
        }
        $data = is_array($request['body']) ? $request['body'] : [];
        $email = isset($data['email']) ? trim((string) $data['email']) : '';
        $password = isset($data['password']) ? (string) $data['password'] : '';
        $nickname = isset($data['nickname']) ? trim((string) $data['nickname']) : '';

        if ($email === '' || $password === '' || $nickname === '') {
            $this->jsonResponse(['error' => 'Missing fields'], 400);
            return true;
        }

        $context = $this->requestContext($request);
        $accountRow = $this->accounts->findAccountByEmail($email, true);
        if ($accountRow === null || !password_verify($password, $accountRow['password_hash'])) {
            if ($accountRow !== null) {
                $this->accounts->logEvent((int) $accountRow['account_id'], 'profile.nickname_change_denied', $context, ['reason' => 'invalid_credentials']);
            }
            $this->jsonResponse(['error' => 'Invalid credentials'], 401);
            return true;
        }
        if ($accountRow['status'] !== 'active') {
            $this->accounts->logEvent((int) $accountRow['account_id'], 'profile.nickname_change_denied', $context, ['reason' => 'inactive', 'status' => $accountRow['status']]);
            $this->jsonResponse(['error' => 'Account not available'], 403);
            return true;
        }

        $result = $this->accounts->changeNickname((int) $accountRow['account_id'], $nickname, (int) $accountRow['account_id'], $context);
        if (!$result['success']) {
            $this->jsonResponse(['error' => 'Unable to update nickname'], 400);
            return true;
        }

        $account = $result['account'];
        $this->jsonResponse([
            'success' => true,
            'account' => [
                'id' => $account['id'],
                'nickname' => $account['nickname'],
            ],
        ]);
        return true;
    }

    private function jsonResponse(array $data, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function requestContext(array $request): array {
        $headers = $request['headers'] ?? [];
        return [
            'ip' => $request['ip'] ?? $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $headers['User-Agent'] ?? $headers['user-agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? null,
            'route' => $request['route'] ?? null,
            'method' => $request['method'] ?? null,
            'source' => !empty($request['is_intranet']) ? 'intranet-api' : 'public-api',
        ];
    }
}
