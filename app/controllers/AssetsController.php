<?php
namespace App\Controllers;

use Core\BaseController;
use Core\File;
use Core\Error;
use Core\ErrorHandler;

class AssetsController extends BaseController {

    // The File class dependency.
    private File $file;

    /**
     * Handles asset requests.
     *
     * @param array $request The routing data package.
     * @return bool True if handled; otherwise false.
     */
    public function handle($request): bool {
        $this->file = $this->container->get(File::class);

        try {
            $this->delegateRoute('/', [$this, 'serveStaticAsset'], $request);

            $path = $request['route'];
            throw new Error(
                'user',
                "Asset not found",
                "Asset '{$path}' not found",
                ['path' => $path],
                404
            );
        } catch (Error $error) {
            if ($error->getBlame() === 'user') {
                header('Content-Type: text/plain; charset=UTF-8');
                http_response_code($error->getHttpCode());
                echo $error->getHumanDetails();
                return true;
            } else {
                $eh = $this->container->get(ErrorHandler::class);
                $eh->handleException($error, 'assets_system');
            }
        }
        return true;
    }

    /**
     * Serves a static asset from 'app/assets/static/'.
     *
     * @param array $request The routing data package.
     * @return bool True if served; otherwise false.
     */
    public function serveStaticAsset($request): bool {
        $route = ltrim($request['subroute'], '/');
        $assetPath = 'app/assets/static/' . $route;

        if (!$this->file->exists($assetPath) || !$this->file->isFile($assetPath)) {
            return false; // continue resolution if static file doesn't exist.
        }
        // file exists, serve it.

        $mime = $this->file->getMime($assetPath);
        $contents = $this->file->read($assetPath);

        header("Content-Type: $mime; charset=UTF-8");
        echo $contents;
        
        return true;
    }
}