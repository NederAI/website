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
        $this->delegateRoute('/accounting/create-account!', [$this, 'createAccount'], $request);
        $this->delegateRoute('/accounting/create-entry!', [$this, 'createJournalEntry'], $request);
        $this->delegateRoute('/accounting!', [$this, 'accountingDashboard'], $request);

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

    public function accountingDashboard($request): bool {
        $this->requireAuth();
        $payload = [
            'user' => $this->user,
            'accounts' => $this->fetchAccounts(),
            'trialBalance' => $this->fetchTrialBalance(),
            'entries' => $this->fetchRecentEntries(),
            'flash' => $this->consumeFlash(),
        ];

        $body = '<div id="app-root"></div>';
        $scripts = $this->appScripts($payload);
        $this->renderLayout('Boekhouding', $body, 'accounting', $scripts);
        return true;
    }

    public function createAccount($request): bool {
        $this->requireAuth();
        if ($request['method'] !== 'POST') {
            $this->redirect('/accounting');
            return true;
        }
        $data = is_array($request['body']) ? $request['body'] : $_POST;
        try {
            $service = $this->container->get(AccountingService::class);
            $account = $service->ensureAccount([
                'code' => $data['code'] ?? '',
                'name' => $data['name'] ?? '',
                'rgs_code' => $data['rgs_code'] ?? null,
                'account_type' => $data['account_type'] ?? null,
                'currency' => $data['currency'] ?? 'EUR',
            ]);

            if ($this->isAjax($request)) {
                $this->json(['success' => true, 'account' => $account]);
                return true;
            }

            $this->setFlash('Rekening opgeslagen.');
        } catch (InvalidArgumentException $e) {
            if ($this->isAjax($request)) {
                $this->json(['success' => false, 'error' => $e->getMessage()], 400);
                return true;
            }
            $this->setFlash('Fout: ' . $e->getMessage());
        } catch (\Throwable $e) {
            if ($this->isAjax($request)) {
                $this->json(['success' => false, 'error' => 'Onbekende fout bij opslaan van rekening.'], 500);
                return true;
            }
            $this->setFlash('Onbekende fout bij opslaan van rekening.');
        }
        $this->redirect('/accounting');
        return true;
    }

    public function createJournalEntry($request): bool {
        $this->requireAuth();
        if ($request['method'] !== 'POST') {
            $this->redirect('/accounting');
            return true;
        }
        $data = is_array($request['body']) ? $request['body'] : $_POST;
        $lines = isset($data['lines']) && is_array($data['lines']) ? $data['lines'] : [];
        try {
            $service = $this->container->get(AccountingService::class);
            $entry = $service->createJournalEntry([
                'journal_code' => $data['journal_code'] ?? 'MEM',
                'journal_name' => $data['journal_code'] ?? 'MEM',
                'entry_date' => $data['entry_date'] ?? null,
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
            ], $lines, true);

            if ($this->isAjax($request)) {
                $this->json(['success' => true, 'entry' => $entry]);
                return true;
            }

            $this->setFlash('Boeking aangemaakt en geboekt.');
        } catch (InvalidArgumentException $e) {
            if ($this->isAjax($request)) {
                $this->json(['success' => false, 'error' => $e->getMessage()], 400);
                return true;
            }
            $this->setFlash('Fout: ' . $e->getMessage());
        } catch (\Throwable $e) {
            if ($this->isAjax($request)) {
                $this->json(['success' => false, 'error' => 'Onbekende fout bij boeken.'], 500);
                return true;
            }
            $this->setFlash('Onbekende fout bij boeken.');
        }
        $this->redirect('/accounting');
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

    private function fetchAccounts(): array {
        $stmt = $this->pdo->query('SELECT code, name, account_type, rgs_code, currency FROM accounting.ledger_accounts ORDER BY code');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fetchTrialBalance(): array {
        $stmt = $this->pdo->query('SELECT account_code, account_name, total_debit, total_credit, balance FROM accounting.trial_balance ORDER BY account_code');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fetchRecentEntries(): array {
        $sql = 'SELECT je.entry_date, j.code AS journal_code, je.reference, je.status, je.description
                FROM accounting.journal_entries je
                JOIN accounting.journals j ON je.journal_id = j.id
                ORDER BY je.entry_date DESC, je.id DESC
                LIMIT 10';
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
