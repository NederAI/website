<?php
namespace App\Controllers\Intranet\Api;

use App\Controllers\Intranet\BaseIntranetController;
use App\Services\Accounting\AccountingService;

class RouterController extends BaseIntranetController {
    private ?AccountingService $accounting = null;

    public function handle($request): bool {
        $this->bootSession();

        $this->delegateRoute('/bootstrap!', [$this, 'bootstrap'], $request);
        $this->delegateRoute('/navigation!', [$this, 'navigation'], $request);
        $this->delegateRoute('/accounting', AccountingController::class, $request);

        $this->json(['error' => 'Not found'], 404);
        return true;
    }

    public function bootstrap($request): bool {
        if (!$this->ensureAuthenticatedJson()) {
            return true;
        }

        $accounting = $this->accounting();
        $organizations = $accounting->listOrganizations();
        $navigation = $this->buildNavigation();

        $payload = [
            'user' => [
                'id' => (int) $this->user['id'],
                'email' => $this->user['email'],
                'nickname' => $this->user['nickname'],
            ],
            'navigation' => $navigation,
            'organizations' => $organizations,
            'apps' => [
                [
                    'id' => 'accounting',
                    'label' => 'Boekhouding',
                    'default_org' => $organizations[0]['code'] ?? null,
                ],
            ],
            'links' => [
                'logout' => '/logout',
            ],
        ];

        $this->json($payload);
        return true;
    }

    public function navigation($request): bool {
        if (!$this->ensureAuthenticatedJson()) {
            return true;
        }
        $this->json(['items' => $this->buildNavigation()]);
        return true;
    }

    private function buildNavigation(): array {
        $accounting = $this->accounting();
        $tree = $accounting->listOrganizationTree();
        $orgNodes = $this->buildOrgNavigation($tree, 'accounting');

        return [
            [
                'id' => 'accounting',
                'label' => 'Boekhouding',
                'icon' => 'ledger',
                'view' => null,
                'context' => [],
                'children' => $orgNodes,
            ],
            [
                'id' => 'settings',
                'label' => 'Instellingen',
                'icon' => 'settings',
                'view' => 'settings.profile',
                'context' => [],
                'children' => [
                    [
                        'id' => 'settings:profile',
                        'label' => 'Profiel',
                        'view' => 'settings.profile',
                        'context' => [],
                        'children' => [],
                    ],
                ],
            ],
        ];
    }

    private function buildOrgNavigation(array $nodes, string $prefix): array {
        $items = [];
        foreach ($nodes as $node) {
            $item = [
                'id' => $prefix . ':' . $node['code'],
                'label' => $node['name'],
                'view' => 'accounting.dashboard',
                'context' => [
                    'org_id' => $node['id'],
                    'org_code' => $node['code'],
                ],
                'children' => $this->buildOrgNavigation($node['children'] ?? [], $prefix),
            ];
            $items[] = $item;
        }
        return $items;
    }

    private function accounting(): AccountingService {
        if ($this->accounting === null) {
            $this->accounting = $this->container->get(AccountingService::class);
        }
        return $this->accounting;
    }
}