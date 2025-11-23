<?php

declare(strict_types=1);

// Define base paths
define('BASE_PATH', dirname(__DIR__));
define('STORAGE_PATH', BASE_PATH . '/storage');

// Load Composer's autoloader
require_once BASE_PATH . '/vendor/autoload.php';

// Load environment variables for testing
$dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->load();

// Override with test environment variables
$_ENV['APP_ENV'] = 'testing';
$_ENV['DB_NAME'] = 'ecommerce_test';
$_ENV['SESSION_DRIVER'] = 'array';

// Initialize session for tests
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_cookies', '0');
    session_start();
}

// Create test database schema helper
function createTestDatabase(): PDO
{
    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $port = $_ENV['DB_PORT'] ?? '5432';
    $dbName = $_ENV['DB_NAME'] ?? 'ecommerce_test';
    $user = $_ENV['DB_USER'] ?? 'postgres';
    $password = $_ENV['DB_PASSWORD'] ?? 'secret';

    $dsn = "pgsql:host={$host};port={$port};dbname={$dbName}";

    try {
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return $pdo;
    } catch (PDOException $e) {
        throw new RuntimeException("Failed to connect to test database: " . $e->getMessage());
    }
}

// Database cleanup helper
function cleanupTestDatabase(PDO $pdo): void
{
    $tables = [
        'order_items',
        'orders',
        'cart_items',
        'carts',
        'products',
        'categories',
        'users'
    ];

    foreach ($tables as $table) {
        $pdo->exec("TRUNCATE TABLE {$table} RESTART IDENTITY CASCADE");
    }
}
