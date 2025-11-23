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
 * Authentication Middleware
 * Ensures user is authenticated before accessing protected routes
 */
final class AuthenticationMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Start session if not started
        SessionHelper::start();

        // Check if user is logged in
        if (!SessionHelper::isLoggedIn()) {
            // Store intended URL for redirect after login
            SessionHelper::set('intended_url', (string) $request->getUri());

            // Flash error message
            SessionHelper::flash('error', 'Please login to continue');

            // Redirect to login page
            return new RedirectResponse('/login');
        }

        // Add user data to request attributes
        $request = $request->withAttribute('user', SessionHelper::getUser());
        $request = $request->withAttribute('user_id', SessionHelper::getUserId());

        return $handler->handle($request);
    }
}
