<?php
namespace App\Controllers\Intranet\Html;

use App\Controllers\Intranet\BaseIntranetController;

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
                $context = [
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                    'route' => $request['route'] ?? '/login',
                    'method' => $request['method'] ?? 'POST',
                    'source' => 'intranet-html',
                ];

                $verification = $this->accountService()->validateCredentials($email, $password, $context);
                if ($verification['success'] ?? false) {
                    $account = $this->accountService()->completeLogin($verification['account']['id'], $context);
                    if (in_array('intranet.member', $account['roles'], true)) {
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = (int) $account['id'];
                        $this->logAccountEvent('login.web', ['nickname' => $account['nickname']]);
                        $this->redirect('/');
                    } else {
                        $error = 'Je hebt geen toegang tot het intranet.';
                    }
                } else {
                    $error = 'Ongeldige inloggegevens.';
                }
            }
        }

        $this->renderLogin($error);
        return true;
    }

    private function logout(): bool {
        if ($this->isAuthenticated()) {
            $this->logAccountEvent('logout', ['source' => 'intranet-html']);
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
            session_regenerate_id(true);
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
