<?php
namespace App\Controllers;

use Core\BaseController;

use App\Controllers\ApiController;

class FrontController extends BaseController {

    /**
     * Handles the front controller logic by filling in routing data from the request
     * and delegating handling to one of the controllers.
     *
     * @param ?array $request Optional mock request, overriding request data when testing
     */
    public function handle($request = null): void {
        // Fill routing package with request information.
        if(!isset($request)){
            $request = $this->getRequestData();
        }

        // Ensure the params key exists (for merging any route parameters).
        if (!isset($request['params'])) {
            $request['params'] = [];
        }
        
        // Delegate to AssetsController if the route begins with /assets.
        $this->delegateRoute('/assets', AssetsController::class, $request);

        // Delegate to ApiController for any API routes.
        $this->delegateRoute('/api', ApiController::class, $request);

        // Delegate to HtmlController for any other routes.
        $this->delegateRoute('/', HtmlController::class, $request);

        // If no controller has handled the request, return a 404 response.
        header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
        echo "404 Not Found";
    }

    private function getRequestData(): array {
        // Basic route and method
        $request['route']  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $request['method'] = $_SERVER['REQUEST_METHOD'];

        // Query parameters (from the URL)
        $request['query'] = $_GET;

        // Request body (useful for POST, PUT, PATCH methods)
        // This will attempt to decode JSON, but falls back to raw string if decoding fails.
        $input = file_get_contents('php://input');
        $decoded = json_decode($input, true);
        $request['body'] = ($decoded !== null) ? $decoded : $input;

        // All HTTP headers of the request (if available)
        if (function_exists('getallheaders')) {
            $request['headers'] = getallheaders();
        } else {
            // Fallback if getallheaders() is not available (e.g., on non-Apache servers)
            $headers = [];
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    // Convert HTTP_HEADER_NAME to Header-Name
                    $headerName = str_replace(' ', '-', ucwords(
                        strtolower(str_replace('_', ' ', substr($name, 5)))
                    ));
                    $headers[$headerName] = $value;
                }
            }
            $request['headers'] = $headers;
        }

        $request['domain'] = $request['headers']['Host'];

        // A timestamp for when the request is processed
        $request['timestamp'] = time();

        // Client IP address
        $request['ip'] = $_SERVER['REMOTE_ADDR'];

        return $request;
    }
}