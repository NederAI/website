<?php
namespace App\Controllers;

use Core\BaseController;

class FrontController extends BaseController {

    /**
     * Handles the front controller logic by filling in routing data from the request
     * and delegating handling to one of the controllers.
     *
     * @param ?array $request Optional mock request, overriding request data when testing
     */
    public function handle($request = null): void {
        if (!isset($request)) {
            $request = $this->getRequestData();
        }

        if (!isset($request['params'])) {
            $request['params'] = [];
        }

        $host = strtolower($request['domain'] ?? '');
        if ($host !== '') {
            $host = explode(':', $host)[0];
        }
        $isIntranet = $host === 'intranet.neder.ai';
        $request['is_intranet'] = $isIntranet;

        $this->delegateRoute('/assets', AssetsController::class, $request);
        $this->delegateRoute('/api', ApiController::class, $request);
        $this->delegateRoute('/', HtmlController::class, $request);

        header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
        echo "404 Not Found";
    }

    private function getRequestData(): array {
        $request = [];
        $request['route']  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $request['method'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $request['query'] = $_GET;

        $input = file_get_contents('php://input');
        $decoded = json_decode($input, true);
        $request['body'] = ($decoded !== null) ? $decoded : $input;

        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } else {
            $headers = [];
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) === 'HTTP_') {
                    $headerName = str_replace(' ', '-', ucwords(
                        strtolower(str_replace('_', ' ', substr($name, 5)))
                    ));
                    $headers[$headerName] = $value;
                }
            }
        }
        $request['headers'] = $headers;

        $headerHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        if ($headerHost === '' && !empty($headers)) {
            $headerHost = $headers['Host'] ?? $headers['host'] ?? '';
        }
        $request['domain'] = strtolower($headerHost);

        $request['timestamp'] = time();
        $request['ip'] = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        return $request;
    }
}