<?php
namespace App\Controllers\Intranet\Api;

use App\Controllers\Intranet\BaseIntranetController;

class RouterController extends BaseIntranetController {
    public function handle($request): bool {
        $this->bootSession();

        $this->delegateRoute('/bootstrap!', [$this, 'bootstrap'], $request);
        $this->delegateRoute('/navigation!', [$this, 'navigation'], $request);

        $this->json(['error' => 'Not found'], 404);
        return true;
    }

    public function bootstrap($request): bool {
        if (!$this->ensureAuthenticatedJson($request)) {
            return true;
        }

        $payload = [
            'user' => [
                'id' => (int) $this->user['id'],
                'email' => $this->user['email'],
                'nickname' => $this->user['nickname'],
                'roles' => $this->roles,
                'last_seen_at' => $this->user['last_seen_at'] ?? null,
            ],
            'navigation' => $this->defaultNavigation(),
            'links' => [
                'logout' => '/logout',
            ],
        ];

        $this->json($payload);
        return true;
    }

    public function navigation($request): bool {
        if (!$this->ensureAuthenticatedJson($request)) {
            return true;
        }

        $this->json([
            'items' => $this->defaultNavigation(),
            'roles' => $this->roles,
        ]);
        return true;
    }

    private function defaultNavigation(): array {
        return [
            [
                'id' => 'home',
                'label' => 'Dashboard',
                'icon' => 'home',
                'view' => 'home.overview',
                'context' => [],
                'children' => [],
            ],
            [
                'id' => 'settings',
                'label' => 'Instellingen',
                'icon' => 'settings',
                'view' => 'settings.profile',
                'context' => [],
                'children' => [],
            ],
        ];
    }
}
