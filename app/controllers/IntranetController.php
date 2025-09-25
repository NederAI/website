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
        $body = '<p class="lead">Welkom bij het intranet, ' . $this->escape($this->user['nickname'] ?? $this->user['email']) . '.</p>'
              . '<ul>'
              . '<li><a href="/accounting">Boekhouding</a></li>'
              . '</ul>';

        $this->renderLayout('Intranet Home', $body);
        return true;
    }

    public function accountingDashboard($request): bool {
        $this->requireAuth();
        $service = $this->container->get(AccountingService::class);
        $flash = $this->consumeFlash();
        $accounts = $this->fetchAccounts();
        $trialBalance = $service->getTrialBalance();
        $recentEntries = $this->fetchRecentEntries();

        $flashHtml = $flash ? '<div class="flash">' . $this->escape($flash) . '</div>' : '';

        $accountRows = '';
        foreach ($accounts as $account) {
            $accountRows .= '<tr>'
                . '<td>' . $this->escape($account['code']) . '</td>'
                . '<td>' . $this->escape($account['name']) . '</td>'
                . '<td>' . $this->escape($account['account_type']) . '</td>'
                . '<td>' . $this->escape($account['rgs_code'] ?? '-') . '</td>'
                . '<td>' . $this->escape($account['currency']) . '</td>'
                . '</tr>';
        }
        if ($accountRows === '') {
            $accountRows = '<tr><td colspan="5" class="empty">Nog geen rekeningen</td></tr>';
        }

        $balanceRows = '';
        foreach ($trialBalance as $line) {
            $balanceRows .= '<tr>'
                . '<td>' . $this->escape($line['account_code']) . '</td>'
                . '<td>' . $this->escape($line['account_name']) . '</td>'
                . '<td class="num">' . number_format((float)$line['total_debit'], 2, ',', '.') . '</td>'
                . '<td class="num">' . number_format((float)$line['total_credit'], 2, ',', '.') . '</td>'
                . '<td class="num">' . number_format((float)$line['balance'], 2, ',', '.') . '</td>'
                . '</tr>';
        }
        if ($balanceRows === '') {
            $balanceRows = '<tr><td colspan="5" class="empty">Nog geen boekingen</td></tr>';
        }

        $entryRows = '';
        foreach ($recentEntries as $entry) {
            $entryRows .= '<tr>'
                . '<td>' . $this->escape($entry['entry_date']) . '</td>'
                . '<td>' . $this->escape($entry['journal_code']) . '</td>'
                . '<td>' . $this->escape($entry['reference'] ?? '-') . '</td>'
                . '<td>' . $this->escape($entry['status']) . '</td>'
                . '<td>' . $this->escape($entry['description'] ?? '-') . '</td>'
                . '</tr>';
        }
        if ($entryRows === '') {
            $entryRows = '<tr><td colspan="5" class="empty">Geen recente boekingen</td></tr>';
        }

        $body = $flashHtml . <<<HTML
<section>
    <h2>Nieuwe grootboekrekening</h2>
    <form method="post" action="/accounting/create-account" class="stack">
        <label>Code
            <input type="text" name="code" required>
        </label>
        <label>Naam
            <input type="text" name="name" required>
        </label>
        <label>RGS-code
            <input type="text" name="rgs_code" placeholder="Bijv. B1">
        </label>
        <label>Rekeningtype
            <select name="account_type">
                <option value="">Automatisch (RGS)</option>
                <option value="asset">Asset</option>
                <option value="liability">Liability</option>
                <option value="equity">Equity</option>
                <option value="revenue">Revenue</option>
                <option value="expense">Expense</option>
                <option value="memo">Memo</option>
            </select>
        </label>
        <label>Valuta
            <input type="text" name="currency" value="EUR">
        </label>
        <button type="submit">Opslaan</button>
    </form>
</section>

<section>
    <h2>Nieuwe boeking</h2>
    <form method="post" action="/accounting/create-entry" class="stack">
        <label>Journaalcode
            <input type="text" name="journal_code" value="MEM" required>
        </label>
        <label>Datum
            <input type="date" name="entry_date" required>
        </label>
        <label>Referentie
            <input type="text" name="reference">
        </label>
        <label>Omschrijving
            <input type="text" name="description">
        </label>
        <fieldset>
            <legend>Regels</legend>
            <p>Voer precies twee regels in. Meer regels kunnen via Adminer of een toekomstig formulier.</p>
            <div class="line-grid">
                <div>
                    <label>Rekening (debet)
                        <input type="text" name="lines[0][account_code]" placeholder="1000" required>
                    </label>
                    <label>Bedrag
                        <input type="number" step="0.01" name="lines[0][amount]" required>
                    </label>
                    <input type="hidden" name="lines[0][direction]" value="debit">
                </div>
                <div>
                    <label>Rekening (credit)
                        <input type="text" name="lines[1][account_code]" placeholder="1600" required>
                    </label>
                    <label>Bedrag
                        <input type="number" step="0.01" name="lines[1][amount]" required>
                    </label>
                    <input type="hidden" name="lines[1][direction]" value="credit">
                </div>
            </div>
        </fieldset>
        <button type="submit">Boeking toevoegen</button>
    </form>
</section>

<section>
    <h2>Grootboekrekeningen</h2>
    <table class="data">
        <thead>
            <tr><th>Code</th><th>Naam</th><th>Type</th><th>RGS</th><th>Valuta</th></tr>
        </thead>
        <tbody>
            {$accountRows}
        </tbody>
    </table>
</section>

<section>
    <h2>Saldi</h2>
    <table class="data">
        <thead>
            <tr><th>Code</th><th>Naam</th><th>Debet</th><th>Credit</th><th>Saldo</th></tr>
        </thead>
        <tbody>
            {$balanceRows}
        </tbody>
    </table>
</section>

<section>
    <h2>Recente boekingen</h2>
    <table class="data">
        <thead>
            <tr><th>Datum</th><th>Journaal</th><th>Referentie</th><th>Status</th><th>Omschrijving</th></tr>
        </thead>
        <tbody>
            {$entryRows}
        </tbody>
    </table>
</section>
HTML;

        $this->renderLayout('Boekhouding', $body, 'accounting');
        return true;
    }

    public function createAccount($request): bool {
        $this->requireAuth();
        if ($request['method'] !== 'POST') {
            $this->redirect('/accounting');
            return true;
        }
        $data = $_POST ?: (is_array($request['body']) ? $request['body'] : []);
        try {
            $service = $this->container->get(AccountingService::class);
            $service->ensureAccount([
                'code' => $data['code'] ?? '',
                'name' => $data['name'] ?? '',
                'rgs_code' => $data['rgs_code'] ?? null,
                'account_type' => $data['account_type'] ?? null,
                'currency' => $data['currency'] ?? 'EUR',
            ]);
            $this->setFlash('Rekening opgeslagen.');
        } catch (InvalidArgumentException $e) {
            $this->setFlash('Fout: ' . $e->getMessage());
        } catch (\Throwable $e) {
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
        $data = $_POST ?: (is_array($request['body']) ? $request['body'] : []);
        $lines = $data['lines'] ?? [];
        try {
            $service = $this->container->get(AccountingService::class);
            $service->createJournalEntry([
                'journal_code' => $data['journal_code'] ?? 'MEM',
                'journal_name' => $data['journal_code'] ?? 'MEM',
                'entry_date' => $data['entry_date'] ?? null,
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
            ], $lines, true);
            $this->setFlash('Boeking aangemaakt en geboekt.');
        } catch (InvalidArgumentException $e) {
            $this->setFlash('Fout: ' . $e->getMessage());
        } catch (\Throwable $e) {
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
        $message = $error ? '<p class="flash">' . $this->escape($error) . '</p>' : '';
        $body = <<<HTML
{$message}
<form method="post" action="/login" class="stack auth">
    <label>E-mail
        <input type="email" name="email" required autofocus>
    </label>
    <label>Wachtwoord
        <input type="password" name="password" required>
    </label>
    <button type="submit">Inloggen</button>
</form>
HTML;
        $this->renderLayout('Inloggen', $body, 'login');
    }

    private function renderLayout(string $title, string $body, string $active = ''): void {
        $nav = '<nav><a href="/"' . ($active === 'home' ? ' class="active"' : '') . '>Home</a>'
            . '<a href="/accounting"' . ($active === 'accounting' ? ' class="active"' : '') . '>Boekhouding</a>'
            . '<a href="/logout">Uitloggen</a></nav>';

        if (!$this->user) {
            $nav = '<nav><a href="/login">Inloggen</a></nav>';
        }

        echo '<!DOCTYPE html>'
            . '<html lang="nl"><head><meta charset="utf-8"><title>' . $this->escape($title) . ' ? Intranet</title>'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<style>' . $this->styles() . '</style>'
            . '</head><body>'
            . '<header><h1>' . $this->escape($title) . '</h1>' . $nav . '</header>'
            . '<main>' . $body . '</main>'
            . '<footer><small>Intranet ? ' . date('Y') . '</small></footer>'
            . '</body></html>';
    }

    private function styles(): string {
        return <<<CSS
:root { font-family: system-ui, sans-serif; background:#f5f6f8; color:#1f2933; }
body { margin:0; }
header { background:#1f2a44; color:#fff; padding:1rem 2rem; display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; }
header h1 { margin:0; font-size:1.5rem; }
nav a { color:#dbeafe; margin-right:1rem; text-decoration:none; font-weight:600; }
nav a.active { text-decoration:underline; }
main { padding:2rem; max-width:960px; margin:0 auto; }
footer { text-align:center; padding:1rem; color:#6b7280; }
form.stack { display:flex; flex-direction:column; gap:0.75rem; background:#fff; padding:1.5rem; border-radius:0.75rem; box-shadow:0 2px 6px rgba(15,23,42,0.15); }
form.stack label { font-weight:600; display:flex; flex-direction:column; gap:0.25rem; }
input, select, button { font:inherit; padding:0.6rem 0.75rem; border-radius:0.5rem; border:1px solid #cbd5f5; }
button { background:#2563eb; border:none; color:#fff; cursor:pointer; }
button:hover { background:#1d4ed8; }
.table-wrapper { overflow:auto; }
table.data { width:100%; border-collapse:collapse; margin-top:1rem; background:#fff; }
table.data th, table.data td { padding:0.6rem 0.75rem; border-bottom:1px solid #e2e8f0; }
table.data th { text-align:left; background:#e2e8f0; }
table.data td.num { text-align:right; }
.flash { background:#dcfce7; color:#14532d; padding:0.75rem 1rem; border-radius:0.75rem; margin-bottom:1rem; }
.auth { max-width:420px; margin:2rem auto; }
.lead { font-size:1.2rem; }
.empty { text-align:center; color:#6b7280; font-style:italic; }
.line-grid { display:grid; gap:1rem; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); }
CSS;
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

