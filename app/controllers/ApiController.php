<?php
namespace App\Controllers;

use Core\BaseController;

class ApiController extends BaseController {
    public function handle($request): bool {
        $this->delegateRoute('/auth', AuthController::class, $request);

        if (!empty($request['is_intranet'])) {
            $this->delegateRoute('/intranet', \App\Controllers\Intranet\Api\RouterController::class, $request);
        }

        return false;
    }
}