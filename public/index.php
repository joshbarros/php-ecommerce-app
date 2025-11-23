<?php

declare(strict_types=1);

use App\Database\Connection;
use DI\ContainerBuilder;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use League\Route\Router;
use League\Route\Strategy\JsonStrategy;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

// Define paths
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/src');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('PUBLIC_PATH', ROOT_PATH . '/public');

// Load Composer autoloader
require ROOT_PATH . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(ROOT_PATH);
$dotenv->load();
$dotenv->required(['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD']);

// Error handling based on environment
if ($_ENV['APP_ENV'] === 'production') {
    ini_set('display_errors', '0');
    error_reporting(0);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

// Start session
session_start();

// Build DI container
$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions(CONFIG_PATH . '/container.php');

try {
    $container = $containerBuilder->build();
} catch (\Exception $e) {
    die('Failed to build container: ' . $e->getMessage());
}

// Create PSR-7 request
$request = ServerRequestFactory::fromGlobals();

// Get router from container
$router = $container->get(Router::class);

// Load routes
require CONFIG_PATH . '/routes.php';

try {
    // Dispatch request
    $response = $router->dispatch($request);

    // Emit response
    (new SapiEmitter())->emit($response);

} catch (\League\Route\Http\Exception\NotFoundException $e) {
    // 404 Not Found
    http_response_code(404);
    if ($_ENV['APP_DEBUG'] === 'true') {
        echo '<h1>404 Not Found</h1>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    } else {
        echo '<h1>Page Not Found</h1>';
    }
} catch (\League\Route\Http\Exception\MethodNotAllowedException $e) {
    // 405 Method Not Allowed
    http_response_code(405);
    echo '<h1>Method Not Allowed</h1>';
} catch (\Throwable $e) {
    // Log error
    try {
        $logger = $container->get(LoggerInterface::class);
        $logger->error('Application error', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
    } catch (\Exception $logError) {
        error_log('Failed to log error: ' . $logError->getMessage());
    }

    // Show error page
    http_response_code(500);
    if ($_ENV['APP_DEBUG'] === 'true') {
        echo '<h1>Application Error</h1>';
        echo '<pre>' . htmlspecialchars($e->__toString()) . '</pre>';
    } else {
        echo '<h1>An error occurred</h1>';
        echo '<p>Please try again later.</p>';
    }
}
