<?php
namespace App\Controllers;

use Core\BaseController;
use Core\Database;
use App\Services\Accounting\AccountingService;
use InvalidArgumentException;
use PDO;

class IntranetController extends BaseController {
    private PDO $pdo;
    private ?array $user = null;

    public function handle($request): bool {
        if (!$request['is_intranet']) {
            return false;
        }

        $this->startSession();
        $this->pdo = $this->container->get(Database::class)->getConnection();
        $this->user = $this->resolveAuthenticatedUser();

        $this->delegateRoute('/login!', [$this, 'login'], $request);
        $this->delegateRoute('/logout!', [$this, 'logout'], $request);

        return $this->dashboard($request);
    }

    private function startSession(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_set_cookie_params([
                'httponly' => true,
                'secure' => true,
                'samesite' => 'Strict',
            ]);
            session_start();
        }
    }

    private function resolveAuthenticatedUser(): ?array {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT id, email, nickname, created_at FROM users WHERE id = :id');
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            unset($_SESSION['user_id']);
            return null;
        }
        return $user;
    }

    public function login($request): bool {
        if ($request['method'] === 'POST') {
            $data = is_array($request['body']) ? $request['body'] : $_POST;
            $email = trim((string)($data['email'] ?? ''));
            $password = (string)($data['password'] ?? '');
            if ($email === '' || $password === '') {
                $this->renderLogin('Vul e-mail en wachtwoord in.');
                return true;
            }
            $stmt = $this->pdo->prepare('SELECT id, password_hash FROM users WHERE email = :email');
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user || !password_verify($password, $user['password_hash'])) {
                $this->renderLogin('Ongeldige inloggegevens.');
                return true;
            }
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            $this->redirect('/');
            return true;
        }

        $this->renderLogin();
        return true;
    }

    public function logout($request): bool {
        $this->requireAuth();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        $this->redirect('/login');
        return true;
    }

    public function dashboard($request): bool {
        if (!$this->user) {
            $this->redirect('/login');
            return true;
        }

        $body = '<div id="app-root"></div>';
        $scripts = $this->appScripts([
            'user' => $this->user,
            'accounts' => $this->fetchAccounts(),
            'trialBalance' => $this->fetchTrialBalance(),
            'entries' => $this->fetchRecentEntries(),
            'flash' => $this->consumeFlash(),
        ]);

        $this->renderLayout('Intranet', $body, 'home', $scripts);
        return true;
    }

    private function requireAuth(): void {
        if (!$this->user) {
            $this->redirect('/login');
            exit;
        }
    }

    private function renderLogin(string $error = ''): void {
        $body = '<div class="login-shell">'
            . '<div class="login-card">'
            . '<h1>Intranet</h1>'
            . ($error ? '<p class="error">' . $this->escape($error) . '</p>' : '')
            . '<form method="post" action="/login" class="login-form">'
            . '    <label>E-mail<input type="email" name="email" required autofocus></label>'
            . '    <label>Wachtwoord<input type="password" name="password" required></label>'
            . '    <button type="submit">Inloggen</button>'
            . '</form>'
            . '</div>'
            . '</div>';

        $this->renderLayout('Inloggen', $body, 'login');
    }

    private function renderLayout(string $title, string $body, string $active = '', string $scripts = ''): void {
        echo '<!DOCTYPE html>'
            . '<html lang="nl">'
            . '<head>'
            . '<meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $this->escape($title) . ' - Intranet</title>'
            . '<link rel="stylesheet" href="/assets/intranet.css">'
            . '</head>'
            . '<body class="intranet">'
            . $body
            . '<script src="/assets/dab-components.js"></script>'
            . $scripts
            . '<script src="/assets/intranet.js"></script>'
            . '</body></html>';
    }

    private function appScripts(array $payload): string {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return '<script>window.INTRANET_DATA = ' . $json . ';</script>';
    }

    private function json($data, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    private function isAjax(array $request): bool {
        $headers = $request['headers'] ?? [];
        $requestedWith = $headers['X-Requested-With'] ?? $headers['x-requested-with'] ?? ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? null);
        return $requestedWith && strtolower($requestedWith) === 'xmlhttprequest';
    }

    private function redirect(string $path): void {
        header('Location: ' . $path);
        exit;
    }

    private function escape(?string $value): string {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }

    private function setFlash(string $message): void {
        $_SESSION['flash'] = $message;
    }

    private function consumeFlash(): ?string {
        if (!isset($_SESSION['flash'])) {
            return null;
        }
        $message = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $message;
    }
}
