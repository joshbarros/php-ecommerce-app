<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\CsrfHelper;
use App\Helpers\SessionHelper;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * CSRF Protection Middleware
 * Validates CSRF tokens on state-changing requests (POST, PUT, DELETE, PATCH)
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Start session
        SessionHelper::start();

        $method = $request->getMethod();

        // Skip CSRF check for safe methods
        if (in_array($method, self::SAFE_METHODS)) {
            return $handler->handle($request);
        }

        // Get token from request
        $parsedBody = $request->getParsedBody();
        $token = null;

        if (is_array($parsedBody)) {
            $token = $parsedBody['csrf_token'] ?? null;
        }

        // If not in body, check headers (for AJAX requests)
        if (!$token) {
            $token = $request->getHeaderLine('X-CSRF-Token');
        }

        // Validate token
        if (!CsrfHelper::validateToken($token)) {
            return new JsonResponse([
                'error' => 'CSRF token validation failed'
            ], 403);
        }

        return $handler->handle($request);
    }
}
