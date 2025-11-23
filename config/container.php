<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Repositories\UserRepository;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use App\Repositories\ProductRepository;
use App\Repositories\Interfaces\CategoryRepositoryInterface;
use App\Repositories\CategoryRepository;
use App\Services\AuthService;
use App\Services\ProductService;
use App\Services\CategoryService;
use League\Route\Router;
use League\Route\Strategy\ApplicationStrategy;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Laminas\Diactoros\ResponseFactory;
use Psr\Http\Message\ResponseFactoryInterface;

return [
    // PSR-3 Logger
    LoggerInterface::class => function (ContainerInterface $c) {
        $logger = new Logger('app');

        // Log to daily rotating files
        $logger->pushHandler(new RotatingFileHandler(
            STORAGE_PATH . '/logs/app.log',
            7, // Keep 7 days
            Logger::DEBUG
        ));

        // Also log errors to a separate file
        $logger->pushHandler(new StreamHandler(
            STORAGE_PATH . '/logs/error.log',
            Logger::ERROR
        ));

        return $logger;
    },

    // Database PDO Connection
    PDO::class => function (ContainerInterface $c) {
        $logger = $c->get(LoggerInterface::class);
        Connection::setLogger($logger);
        return Connection::getInstance();
    },

    // PSR-17 Response Factory
    ResponseFactoryInterface::class => function () {
        return new ResponseFactory();
    },

    // Router
    Router::class => function (ContainerInterface $c) {
        $strategy = new ApplicationStrategy();
        $strategy->setContainer($c);

        $router = new Router();
        $router->setStrategy($strategy);

        return $router;
    },

    // Redis Connection
    Redis::class => function (ContainerInterface $c) {
        $redis = new Redis();

        try {
            $redis->connect(
                $_ENV['REDIS_HOST'] ?? 'localhost',
                (int) ($_ENV['REDIS_PORT'] ?? 6379)
            );

            if (!empty($_ENV['REDIS_PASSWORD'])) {
                $redis->auth($_ENV['REDIS_PASSWORD']);
            }

            $logger = $c->get(LoggerInterface::class);
            $logger->info('Redis connection established');

        } catch (Exception $e) {
            $logger = $c->get(LoggerInterface::class);
            $logger->error('Redis connection failed', ['error' => $e->getMessage()]);
            throw $e;
        }

        return $redis;
    },

    // ==================================
    // Repositories
    // ==================================

    UserRepositoryInterface::class => function (ContainerInterface $c) {
        return new UserRepository($c->get(PDO::class));
    },

    ProductRepositoryInterface::class => function (ContainerInterface $c) {
        return new ProductRepository($c->get(PDO::class));
    },

    CategoryRepositoryInterface::class => function (ContainerInterface $c) {
        return new CategoryRepository($c->get(PDO::class));
    },

    // ==================================
    // Services
    // ==================================

    AuthService::class => function (ContainerInterface $c) {
        return new AuthService(
            $c->get(UserRepositoryInterface::class),
            $c->get(LoggerInterface::class)
        );
    },

    ProductService::class => function (ContainerInterface $c) {
        return new ProductService(
            $c->get(ProductRepositoryInterface::class),
            $c->get(LoggerInterface::class)
        );
    },

    CategoryService::class => function (ContainerInterface $c) {
        return new CategoryService(
            $c->get(CategoryRepositoryInterface::class),
            $c->get(LoggerInterface::class)
        );
    },
];
