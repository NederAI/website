<?php
namespace App\Controllers\Intranet\Html;

use App\Controllers\Intranet\BaseIntranetController;

class RouterController extends BaseIntranetController {
    public function handle($request): bool {
        $this->bootSession();

        $this->delegateRoute('/login!', AuthController::class, $request);
        $this->delegateRoute('/logout!', AuthController::class, $request);
        $this->delegateRoute('/', ShellController::class, $request);

        http_response_code(404);
        echo 'Not Found';
        return true;
    }
}