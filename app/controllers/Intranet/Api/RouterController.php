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
        if (!$this->ensureAuthenticatedJson()) {
            return true;
        }

        $payload = [
            'user' => [
                'id' => (int) $this->user['id'],
                'email' => $this->user['email'],
                'nickname' => $this->user['nickname'],
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
        if (!$this->ensureAuthenticatedJson()) {
            return true;
        }

        $this->json(['items' => $this->defaultNavigation()]);
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
