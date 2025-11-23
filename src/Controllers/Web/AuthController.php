<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AuthController
{
    public function showLogin(ServerRequestInterface $request): ResponseInterface
    {
        return new HtmlResponse('<h1>Login Page</h1><p>Coming soon...</p>');
    }

    public function login(ServerRequestInterface $request): ResponseInterface
    {
        // TODO: Implement login logic
        return new RedirectResponse('/');
    }

    public function showRegister(ServerRequestInterface $request): ResponseInterface
    {
        return new HtmlResponse('<h1>Register Page</h1><p>Coming soon...</p>');
    }

    public function register(ServerRequestInterface $request): ResponseInterface
    {
        // TODO: Implement registration logic
        return new RedirectResponse('/');
    }

    public function logout(ServerRequestInterface $request): ResponseInterface
    {
        // TODO: Implement logout logic
        session_destroy();
        return new RedirectResponse('/');
    }
}
