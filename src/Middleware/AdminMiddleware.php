<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\SessionHelper;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class AdminMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Check if user is logged in
        if (!SessionHelper::isLoggedIn()) {
            SessionHelper::set('intended_url', (string) $request->getUri());
            SessionHelper::flash('error', 'Please login to access the admin panel');
            return new RedirectResponse('/login');
        }

        // Check if user has admin role
        $user = SessionHelper::get('user');

        if (!$user || ($user['role'] ?? 'customer') !== 'admin') {
            SessionHelper::flash('error', 'You do not have permission to access the admin panel');
            return new RedirectResponse('/');
        }

        return $handler->handle($request);
    }
}
