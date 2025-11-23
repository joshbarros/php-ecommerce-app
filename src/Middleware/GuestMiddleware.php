<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\SessionHelper;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Guest Middleware
 * Redirects authenticated users away from guest-only pages (login, register)
 */
final class GuestMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Start session if not started
        SessionHelper::start();

        // If user is logged in, redirect to home
        if (SessionHelper::isLoggedIn()) {
            return new RedirectResponse('/');
        }

        return $handler->handle($request);
    }
}
