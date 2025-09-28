<?php
namespace App\Controllers\Intranet\Html;

use App\Controllers\Intranet\BaseIntranetController;

class ShellController extends BaseIntranetController {
    public function handle($request): bool {
        $this->bootSession();
        $this->requireAuthenticated();

        $bootstrap = [
            'user' => [
                'id' => (int) $this->user['id'],
                'email' => $this->user['email'],
                'nickname' => $this->user['nickname'],
            ],
        ];
        $json = json_encode($bootstrap, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        echo '<!DOCTYPE html>'
            . '<html lang="nl">'
            . '<head>'
            . '<meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Intranet</title>'
            . '<link rel="stylesheet" href="/assets/brand.css">'
            . '<link rel="stylesheet" href="/assets/intranet.css">'
            . '<script src="/assets/dab-components.js" defer></script>'
            . '<script>window.INTRANET_BOOTSTRAP = ' . $json . ';</script>'
            . '<script src="/assets/intranet.js" defer></script>'
            . '</head>'
            . '<body class="intranet">'
            . '<div id="intranet-root" class="intranet-shell">'
            . '<aside class="intranet-nav" data-role="nav"></aside>'
            . '<main class="intranet-main" data-role="main"></main>'
            . '</div>'
            . '</body>'
            . '</html>';
        return true;
    }
}