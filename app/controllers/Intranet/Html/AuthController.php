<?php
namespace App\Controllers\Intranet\Html;

use App\Controllers\Intranet\BaseIntranetController;
use PDO;

class AuthController extends BaseIntranetController {
    public function handle($request): bool {
        $this->bootSession();

        $path = rtrim($request['route'] ?? '/', '/');
        if ($path === '/logout') {
            return $this->logout();
        }

        return $this->login($request);
    }

    private function login(array $request): bool {
        if ($this->isAuthenticated()) {
            $this->redirect('/');
        }

        $error = '';
        if (($request['method'] ?? 'GET') === 'POST') {
            $data = $_POST;
            $email = trim((string) ($data['email'] ?? ''));
            $password = (string) ($data['password'] ?? '');
            if ($email === '' || $password === '') {
                $error = 'Vul e-mailadres en wachtwoord in.';
            } else {
                $stmt = $this->pdo->prepare('SELECT id, password_hash, nickname FROM users WHERE email = :email');
                $stmt->execute([':email' => $email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user && password_verify($password, $user['password_hash'])) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int) $user['id'];
                    $this->redirect('/');
                }
                $error = 'Ongeldige inloggegevens.';
            }
        }

        $this->renderLogin($error);
        return true;
    }

    private function logout(): bool {
        if ($this->isAuthenticated()) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
        }
        $this->redirect('/login');
        return true;
    }

    private function renderLogin(string $error): void {
        $escapedError = $error !== '' ? '<p class="error">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>' : '';
        echo '<!DOCTYPE html>'
            . '<html lang="nl">'
            . '<head>'
            . '<meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Intranet - Inloggen</title>'
            . '<script src="/assets/dab-components.js" defer></script>'
            . '<link rel="stylesheet" href="/assets/intranet.css">'
            . '</head>'
            . '<body class="intranet intranet-login">'
            . '<main class="login-panel">'
            . '<section class="login-card">'
            . '<h1>Intranet</h1>'
            . $escapedError
            . '<form method="post" action="/login" class="login-form">'
            . '<label>E-mail<input type="email" name="email" required autofocus></label>'
            . '<label>Wachtwoord<input type="password" name="password" required></label>'
            . '<button type="submit">Inloggen</button>'
            . '</form>'
            . '</section>'
            . '</main>'
            . '</body></html>';
    }
}