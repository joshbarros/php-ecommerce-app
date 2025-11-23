<?php

declare(strict_types=1);

use App\Controllers\Web\HomeController;
use App\Controllers\Web\ProductController;
use App\Controllers\Web\AuthController;
use App\Controllers\Web\CartController;
use League\Route\Router;

/**
 * @var Router $router
 */

// ============================================
// WEB ROUTES
// ============================================

// Home
$router->map('GET', '/', [HomeController::class, 'index']);

// Authentication
$router->map('GET', '/login', [AuthController::class, 'showLogin']);
$router->map('POST', '/login', [AuthController::class, 'login']);
$router->map('GET', '/register', [AuthController::class, 'showRegister']);
$router->map('POST', '/register', [AuthController::class, 'register']);
$router->map('POST', '/logout', [AuthController::class, 'logout']);

// Products
$router->map('GET', '/products', [ProductController::class, 'index']);
$router->map('GET', '/products/{slug:slug}', [ProductController::class, 'show']);
$router->map('GET', '/categories/{slug:slug}', [ProductController::class, 'category']);
$router->map('GET', '/search', [ProductController::class, 'search']);

// Shopping Cart
$router->map('GET', '/cart', [CartController::class, 'index']);
$router->map('POST', '/cart/add', [CartController::class, 'add']);
$router->map('POST', '/cart/update', [CartController::class, 'update']);
$router->map('POST', '/cart/remove', [CartController::class, 'remove']);
$router->map('POST', '/cart/clear', [CartController::class, 'clear']);
$router->map('GET', '/cart/count', [CartController::class, 'count']);

// Health check endpoint
$router->map('GET', '/health', function () {
    return new \Laminas\Diactoros\Response\JsonResponse([
        'status' => 'ok',
        'timestamp' => date('c'),
        'environment' => $_ENV['APP_ENV'] ?? 'unknown'
    ]);
});
