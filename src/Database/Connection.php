<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;
use Psr\Log\LoggerInterface;

final class Connection
{
    private static ?PDO $instance = null;
    private static ?LoggerInterface $logger = null;

    private function __construct()
    {
        // Prevent direct instantiation
    }

    public static function setLogger(LoggerInterface $logger): void
    {
        self::$logger = $logger;
    }

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            try {
                $dsn = sprintf(
                    'pgsql:host=%s;port=%s;dbname=%s;options=\'--client_encoding=UTF8\'',
                    $_ENV['DB_HOST'] ?? 'localhost',
                    $_ENV['DB_PORT'] ?? '5432',
                    $_ENV['DB_NAME'] ?? 'ecommerce'
                );

                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_STRINGIFY_FETCHES => false,
                    PDO::ATTR_PERSISTENT => false,
                ];

                self::$instance = new PDO(
                    $dsn,
                    $_ENV['DB_USER'] ?? 'postgres',
                    $_ENV['DB_PASSWORD'] ?? '',
                    $options
                );

                self::$logger?->info('Database connection established');

            } catch (PDOException $e) {
                self::$logger?->critical('Database connection failed', [
                    'error' => $e->getMessage()
                ]);

                throw new \RuntimeException(
                    'Database connection failed: ' . $e->getMessage(),
                    0,
                    $e
                );
            }
        }

        return self::$instance;
    }

    public static function disconnect(): void
    {
        self::$instance = null;
        self::$logger?->info('Database connection closed');
    }

    /**
     * Prevent cloning of the instance
     */
    private function __clone()
    {
    }

    /**
     * Prevent unserializing of the instance
     */
    public function __wakeup(): void
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}
