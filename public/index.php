<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

// The application's entry point.

// Get the controller class
require __DIR__ . '/../core/Container.php';

// Create and configure the container.
$container = new Core\Container();
$container->registerNamespaces([
    // Map top-level namespaces to folder paths.
    'Core' => __DIR__ . '/../core/',
    'App'  => __DIR__ . '/../app/',
    'Lib'  => __DIR__ . '/../lib/'
]);

// Print errors to the screen in development
$container->setMock(Core\ErrorHandler::class, Core\DevelopmentErrorHandler::class);

$eh = $container->get(Core\ErrorHandler::class);

try{
    // Hard dispatch the front controller
    $frontController = $container->get(App\Controllers\FrontController::class);
    $frontController->handle();
    // and there it goes. Whoosh!
} catch (Throwable $uncaught) {
    $eh->handleException($uncaught, 'root');
}