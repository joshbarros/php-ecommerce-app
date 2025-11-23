<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use Laminas\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ProductController
{
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        return new HtmlResponse('<h1>Products</h1><p>Product listing coming soon...</p>');
    }

    public function show(ServerRequestInterface $request, array $args): ResponseInterface
    {
        $slug = $args['slug'] ?? 'unknown';
        return new HtmlResponse("<h1>Product: {$slug}</h1><p>Product details coming soon...</p>");
    }

    public function category(ServerRequestInterface $request, array $args): ResponseInterface
    {
        $slug = $args['slug'] ?? 'unknown';
        return new HtmlResponse("<h1>Category: {$slug}</h1><p>Category products coming soon...</p>");
    }

    public function search(ServerRequestInterface $request): ResponseInterface
    {
        $query = $request->getQueryParams()['q'] ?? '';
        return new HtmlResponse("<h1>Search Results</h1><p>Searching for: {$query}</p>");
    }
}
