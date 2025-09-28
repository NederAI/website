<?php
namespace App\Controllers\Intranet\Api;

use App\Controllers\Intranet\BaseIntranetController;
use App\Services\Accounting\AccountingService;
use InvalidArgumentException;

class AccountingController extends BaseIntranetController {
    private ?AccountingService $accounting = null;

    public function handle($request): bool {
        if (!$this->ensureAuthenticatedJson()) {
            return true;
        }

        $this->delegateRoute('/organizations!', [$this, 'organizations'], $request);
        $this->delegateRoute('/organizations/{code}/snapshot!', [$this, 'snapshot'], $request);
        $this->delegateRoute('/organizations/{code}/accounts!', [$this, 'accounts'], $request);
        $this->delegateRoute('/organizations/{code}/entries!', [$this, 'entries'], $request);

        $this->json(['error' => 'Not found'], 404);
        return true;
    }

    public function organizations($request): bool {
        if (($request['method'] ?? 'GET') !== 'GET') {
            $this->json(['error' => 'Method not allowed'], 405);
            return true;
        }
        $this->json(['items' => $this->accounting()->listOrganizationTree()]);
        return true;
    }

    public function snapshot($request): bool {
        if (($request['method'] ?? 'GET') !== 'GET') {
            $this->json(['error' => 'Method not allowed'], 405);
            return true;
        }
        $status = 0;
        $org = $this->resolveOrganization($request, $status);
        if (!$org) {
            $message = $status === 400 ? 'Organisatie ontbreekt.' : 'Organisatie niet gevonden';
            $this->json(['error' => $message], $status === 0 ? 404 : $status);
            return true;
        }
        $service = $this->accounting();
        $this->json([
            'organization' => $org,
            'accounts' => $service->listAccounts($org['id']),
            'trialBalance' => $service->getTrialBalance($org['id']),
            'entries' => $service->listEntries($org['id']),
        ]);
        return true;
    }

    public function accounts($request): bool {
        $status = 0;
        $org = $this->resolveOrganization($request, $status);
        if (!$org) {
            $message = $status === 400 ? 'Organisatie ontbreekt.' : 'Organisatie niet gevonden';
            $this->json(['error' => $message], $status === 0 ? 404 : $status);
            return true;
        }

        $service = $this->accounting();
        $method = $request['method'] ?? 'GET';

        if ($method === 'GET') {
            $this->json(['items' => $service->listAccounts($org['id'])]);
            return true;
        }

        if ($method === 'POST') {
            try {
                $payload = $this->parseJsonBody($request);
                $account = $service->createAccount($org['id'], $payload);
                $this->json(['account' => $account], 201);
            } catch (InvalidArgumentException $e) {
                $this->json(['error' => $e->getMessage()], 422);
            }
            return true;
        }

        $this->json(['error' => 'Method not allowed'], 405);
        return true;
    }

    public function entries($request): bool {
        $status = 0;
        $org = $this->resolveOrganization($request, $status);
        if (!$org) {
            $message = $status === 400 ? 'Organisatie ontbreekt.' : 'Organisatie niet gevonden';
            $this->json(['error' => $message], $status === 0 ? 404 : $status);
            return true;
        }

        $service = $this->accounting();
        $method = $request['method'] ?? 'GET';

        if ($method === 'GET') {
            $limit = isset($request['query']['limit']) ? (int) $request['query']['limit'] : 25;
            $this->json(['items' => $service->listEntries($org['id'], $limit)]);
            return true;
        }

        if ($method === 'POST') {
            try {
                $payload = $this->parseJsonBody($request);
                $lines = $payload['lines'] ?? [];
                unset($payload['lines']);
                $entry = $service->createEntry($org['id'], $payload, $lines);
                $this->json(['entry' => $entry], 201);
            } catch (InvalidArgumentException $e) {
                $this->json(['error' => $e->getMessage()], 422);
            }
            return true;
        }

        $this->json(['error' => 'Method not allowed'], 405);
        return true;
    }

    private function resolveOrganization(array $request, int &$status): ?array {
        $status = 0;
        $code = $request['params']['code'] ?? null;
        if ($code === null || trim((string) $code) === '') {
            $status = 400;
            return null;
        }
        $service = $this->accounting();
        $org = $service->getOrganizationByCode($code);
        if ($org === null) {
            $status = 404;
            return null;
        }
        return $org;
    }

    private function parseJsonBody(array $request): array {
        $body = $request['body'] ?? null;
        if (is_array($body)) {
            return $body;
        }
        if (is_string($body) && $body !== '') {
            $decoded = json_decode($body, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        if (!empty($_POST)) {
            return $_POST;
        }
        return [];
    }

    private function accounting(): AccountingService {
        if ($this->accounting === null) {
            $this->accounting = $this->container->get(AccountingService::class);
        }
        return $this->accounting;
    }
}