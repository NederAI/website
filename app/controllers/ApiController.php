<?php
namespace App\Controllers;

use Core\BaseController;
use App\Controllers\AuthController;

class ApiController extends BaseController {
    public function handle($request): bool {
        // Delegate authentication routes
        $this->delegateRoute('/auth', AuthController::class, $request);
        return false;
    }
}
