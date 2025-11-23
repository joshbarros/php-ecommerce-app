<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class CartController
{
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        return new HtmlResponse('<h1>Shopping Cart</h1><p>Cart view coming soon...</p>');
    }

    public function add(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse(['success' => true, 'message' => 'Item added to cart']);
    }

    public function update(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse(['success' => true, 'message' => 'Cart updated']);
    }

    public function remove(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse(['success' => true, 'message' => 'Item removed from cart']);
    }

    public function clear(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse(['success' => true, 'message' => 'Cart cleared']);
    }
}
