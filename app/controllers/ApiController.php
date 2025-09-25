<?php
namespace App\Controllers;

use Core\BaseController;
use Core\Database;
use App\Controllers\AuthController;
use App\Services\Accounting\RgsRepository;
use PDO;

class ApiController extends BaseController {
    public function handle($request): bool {
        $this->delegateRoute('/auth', AuthController::class, $request);
        $this->delegateRoute('/intranet/rgs!', [$this, 'rgsLookup'], $request);
        $this->delegateRoute('/intranet/snapshot!', [$this, 'snapshot'], $request);
        return false;
    }

    public function snapshot($request): bool {
        if (!$this->ensureIntranetSession()) {
            $this->json(['error' => 'Unauthorized'], 401);
            return true;
        }

        $db = $this->container->get(Database::class);
        $pdo = $db->getConnection();

        $accounts = $pdo->query('SELECT code, name, account_type, rgs_code, currency FROM accounting.ledger_accounts ORDER BY code')->fetchAll(PDO::FETCH_ASSOC);
        $trial = $pdo->query('SELECT account_code, account_name, total_debit, total_credit, balance FROM accounting.trial_balance ORDER BY account_code')->fetchAll(PDO::FETCH_ASSOC);
        $entries = $pdo->query('SELECT je.entry_date, j.code AS journal_code, je.reference, je.status, je.description FROM accounting.journal_entries je JOIN accounting.journals j ON je.journal_id = j.id ORDER BY je.entry_date DESC, je.id DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);

        $this->json([
            'accounts' => $accounts,
            'trialBalance' => $trial,
            'entries' => $entries,
        ]);
        return true;
    }

    public function rgsLookup($request): bool {
        if ($request['method'] !== 'GET') {
            $this->json(['error' => 'Method not allowed'], 405);
            return true;
        }

        if (!$this->ensureIntranetSession()) {
            $this->json(['error' => 'Unauthorized'], 401);
            return true;
        }

        $query = trim((string)($request['query']['q'] ?? ''));
        $limit = (int)($request['query']['limit'] ?? 25);
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 100) {
            $limit = 100;
        }

        /** @var RgsRepository $repo */
        $repo = $this->container->get(RgsRepository::class);
        $records = $query === '' ? [] : $repo->search($query, $limit);

        $items = array_map(function(array $row){
            return [
                'code' => $row['code'],
                'title' => $row['title_nl'],
                'account_type' => $row['account_type'],
                'level' => isset($row['level']) ? (int)$row['level'] : null,
                'parent_code' => $row['parent_code'] ?? null,
                'function_label' => $row['function_label'] ?? null,
                'is_postable' => in_array($row['is_postable'], ['t', 'true', 1, true], true),
            ];
        }, $records);

        $this->json(['items' => $items]);
        return true;
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
