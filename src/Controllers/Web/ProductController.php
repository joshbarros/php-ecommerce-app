<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Exceptions\NotFoundException;
use App\Services\CategoryService;
use App\Services\ProductService;
use App\Helpers\SessionHelper;
use Laminas\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final class ProductController
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly CategoryService $categoryService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Product listing page
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        SessionHelper::start();

        $queryParams = $request->getQueryParams();
        $page = max(1, (int) ($queryParams['page'] ?? 1));

        $result = $this->productService->getAllProducts($page, 20);
        $categories = $this->categoryService->getRootCategories();

        $html = $this->renderProductList($result, $categories);

        return new HtmlResponse($html);
    }

    /**
     * Product detail page
     */
    public function show(ServerRequestInterface $request, array $args): ResponseInterface
    {
        SessionHelper::start();

        $slug = $args['slug'] ?? '';

        try {
            $product = $this->productService->getProductBySlug($slug);
            $relatedProducts = $product['category_id']
                ? $this->productService->getProductsByCategory($product['category_id'], 1, 4)['products']
                : [];

            $html = $this->renderProductDetail($product, $relatedProducts);

            return new HtmlResponse($html);
        } catch (NotFoundException $e) {
            return new HtmlResponse('<h1>Product Not Found</h1>', 404);
        }
    }

    /**
     * Category products page
     */
    public function category(ServerRequestInterface $request, array $args): ResponseInterface
    {
        SessionHelper::start();

        $slug = $args['slug'] ?? '';
        $queryParams = $request->getQueryParams();
        $page = max(1, (int) ($queryParams['page'] ?? 1));

        try {
            $category = $this->categoryService->getCategoryBySlug($slug);
            $result = $this->productService->getProductsByCategory($category['id'], $page, 20);
            $result['category'] = $category;

            $categories = $this->categoryService->getRootCategories();

            $html = $this->renderProductList($result, $categories);

            return new HtmlResponse($html);
        } catch (NotFoundException $e) {
            return new HtmlResponse('<h1>Category Not Found</h1>', 404);
        }
    }

    /**
     * Search products
     */
    public function search(ServerRequestInterface $request): ResponseInterface
    {
        SessionHelper::start();

        $queryParams = $request->getQueryParams();
        $query = $queryParams['q'] ?? '';
        $page = max(1, (int) ($queryParams['page'] ?? 1));

        $result = $this->productService->searchProducts($query, $page, 20);
        $categories = $this->categoryService->getRootCategories();

        $html = $this->renderProductList($result, $categories);

        return new HtmlResponse($html);
    }

    /**
     * Render product listing
     */
    private function renderProductList(array $result, array $categories): string
    {
        $products = $result['products'];
        $page = $result['page'];
        $totalPages = $result['totalPages'];
        $total = $result['total'];
        $query = $result['query'] ?? null;
        $categoryName = $result['category']['name'] ?? null;

        $pageTitle = $categoryName ?? ($query ? "Search: {$query}" : "All Products");

        // Generate product cards
        $productsHtml = '';
        foreach ($products as $product) {
            $price = $this->productService->formatPrice($product['price']);
            $comparePrice = $product['compare_price']
                ? $this->productService->formatPrice($product['compare_price'])
                : null;
            $discount = $product['compare_price']
                ? $this->productService->getDiscountPercentage($product['price'], $product['compare_price'])
                : null;

            $imageUrl = $product['primary_image'] ?? '/assets/images/placeholder.jpg';
            $slug = htmlspecialchars($product['slug']);
            $name = htmlspecialchars($product['name']);
            $shortDesc = htmlspecialchars($product['short_description'] ?? '');
            $stock = $product['stock_quantity'];

            $discountBadge = $discount ? "<div class=\"badge discount\">-{$discount}%</div>" : '';
            $stockBadge = $stock < 1 ? '<div class="badge out-of-stock">Out of Stock</div>' : '';
            $comparePriceHtml = $comparePrice ? "<span class=\"compare-price\">{$comparePrice}</span>" : '';

            $productsHtml .= <<<HTML
<div class="product-card">
    {$discountBadge}
    {$stockBadge}
    <a href="/products/{$slug}" class="product-image">
        <img src="{$imageUrl}" alt="{$name}">
    </a>
    <div class="product-info">
        <div class="product-category">{$product['category_name']}</div>
        <h3 class="product-title"><a href="/products/{$slug}">{$name}</a></h3>
        <p class="product-description">{$shortDesc}</p>
        <div class="product-footer">
            <div class="product-price">
                {$comparePriceHtml}
                <span class="price">{$price}</span>
            </div>
            <a href="/products/{$slug}" class="btn-primary">View Details</a>
        </div>
    </div>
</div>
HTML;
        }

        if (empty($products)) {
            $productsHtml = '<div class="no-products">No products found.</div>';
        }

        // Generate pagination
        $paginationHtml = '';
        if ($totalPages > 1) {
            $paginationHtml = '<div class="pagination">';

            if ($page > 1) {
                $prevPage = $page - 1;
                $paginationHtml .= "<a href=\"?page={$prevPage}\" class=\"page-link\">← Previous</a>";
            }

            for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++) {
                $active = $i === $page ? 'active' : '';
                $paginationHtml .= "<a href=\"?page={$i}\" class=\"page-link {$active}\">{$i}</a>";
            }

            if ($page < $totalPages) {
                $nextPage = $page + 1;
                $paginationHtml .= "<a href=\"?page={$nextPage}\" class=\"page-link\">Next →</a>";
            }

            $paginationHtml .= '</div>';
        }

        // Categories sidebar
        $categoriesHtml = '';
        foreach ($categories as $cat) {
            $catName = htmlspecialchars($cat['name']);
            $catSlug = htmlspecialchars($cat['slug']);
            $count = $cat['product_count'];
            $categoriesHtml .= "<a href=\"/categories/{$catSlug}\" class=\"category-item\">{$catName} ({$count})</a>";
        }

        $user = SessionHelper::getUser();
        $authLinks = $user
            ? '<a href="/account">Account</a><form method="POST" action="/logout" style="display:inline;"><button type="submit" class="link-button">Logout</button></form>'
            : '<a href="/login">Login</a><a href="/register">Register</a>';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$pageTitle} | E-Commerce</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header-content { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 24px; font-weight: bold; text-decoration: none; color: white; }
        .nav { display: flex; gap: 20px; align-items: center; }
        .nav a, .link-button { color: white; text-decoration: none; transition: opacity 0.2s; background: none; border: none; color: white; cursor: pointer; font-size: 16px; }
        .nav a:hover, .link-button:hover { opacity: 0.8; }
        .search-bar { background: white; padding: 10px; border-radius: 6px; display: flex; max-width: 400px; }
        .search-bar input { border: none; flex: 1; outline: none; }
        .search-bar button { background: #667eea; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; }
        .container { max-width: 1200px; margin: 0 auto; padding: 40px 20px; display: grid; grid-template-columns: 250px 1fr; gap: 30px; }
        .sidebar { background: #f7fafc; padding: 20px; border-radius: 8px; height: fit-content; }
        .sidebar h3 { margin-bottom: 15px; color: #667eea; }
        .category-item { display: block; padding: 10px; margin-bottom: 5px; color: #333; text-decoration: none; border-radius: 4px; transition: background 0.2s; }
        .category-item:hover { background: #e2e8f0; }
        .main-content h1 { margin-bottom: 10px; }
        .results-info { color: #666; margin-bottom: 30px; }
        .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 25px; }
        .product-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); transition: transform 0.2s, box-shadow 0.2s; position: relative; }
        .product-card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0,0,0,0.15); }
        .product-image { display: block; position: relative; padding-top: 100%; overflow: hidden; background: #f7fafc; }
        .product-image img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; }
        .badge { position: absolute; top: 10px; right: 10px; background: #f56565; color: white; padding: 5px 10px; border-radius: 4px; font-size: 12px; font-weight: 600; z-index: 1; }
        .badge.discount { background: #48bb78; }
        .badge.out-of-stock { background: #718096; }
        .product-info { padding: 20px; }
        .product-category { color: #667eea; font-size: 12px; text-transform: uppercase; font-weight: 600; margin-bottom: 5px; }
        .product-title { margin-bottom: 10px; }
        .product-title a { color: #333; text-decoration: none; font-size: 18px; }
        .product-title a:hover { color: #667eea; }
        .product-description { color: #666; font-size: 14px; margin-bottom: 15px; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .product-footer { display: flex; justify-content: space-between; align-items: center; }
        .product-price { display: flex; flex-direction: column; gap: 5px; }
        .compare-price { color: #999; text-decoration: line-through; font-size: 14px; }
        .price { color: #667eea; font-size: 24px; font-weight: bold; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600; transition: transform 0.2s; display: inline-block; border: none; cursor: pointer; }
        .btn-primary:hover { transform: translateY(-2px); }
        .pagination { display: flex; gap: 10px; justify-content: center; margin-top: 40px; }
        .page-link { padding: 10px 15px; background: white; border: 2px solid #e2e8f0; border-radius: 6px; text-decoration: none; color: #333; transition: all 0.2s; }
        .page-link:hover, .page-link.active { background: #667eea; color: white; border-color: #667eea; }
        .no-products { text-align: center; padding: 60px 20px; color: #999; }
        @media (max-width: 768px) {
            .container { grid-template-columns: 1fr; }
            .sidebar { display: none; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <a href="/" class="logo">🛍️ E-Commerce</a>
            <form action="/search" method="GET" class="search-bar">
                <input type="text" name="q" placeholder="Search products..." value="{$query}">
                <button type="submit">Search</button>
            </form>
            <div class="nav">
                <a href="/cart">🛒 Cart</a>
                {$authLinks}
            </div>
        </div>
    </div>

    <div class="container">
        <aside class="sidebar">
            <h3>Categories</h3>
            <a href="/products" class="category-item">All Products ({$total})</a>
            {$categoriesHtml}
        </aside>

        <main class="main-content">
            <h1>{$pageTitle}</h1>
            <div class="results-info">Showing {$total} products</div>

            <div class="product-grid">
                {$productsHtml}
            </div>

            {$paginationHtml}
        </main>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Render product detail page
     */
    private function renderProductDetail(array $product, array $relatedProducts): string
    {
        $name = htmlspecialchars($product['name']);
        $price = $this->productService->formatPrice($product['price']);
        $comparePrice = $product['compare_price']
            ? $this->productService->formatPrice($product['compare_price'])
            : null;
        $discount = $product['compare_price']
            ? $this->productService->getDiscountPercentage($product['price'], $product['compare_price'])
            : null;

        $imageUrl = $product['primary_image'] ?? '/assets/images/placeholder.jpg';
        $description = nl2br(htmlspecialchars($product['description'] ?? 'No description available.'));
        $stock = $product['stock_quantity'];
        $sku = htmlspecialchars($product['sku']);
        $category = htmlspecialchars($product['category_name'] ?? 'Uncategorized');

        $discountBadge = $discount ? "<span class=\"badge discount\">Save {$discount}%</span>" : '';
        $stockStatus = $stock > 0
            ? "<span class=\"stock in-stock\">✓ In Stock ({$stock} available)</span>"
            : "<span class=\"stock out-of-stock\">✗ Out of Stock</span>";
        $comparePriceHtml = $comparePrice ? "<div class=\"compare-price\">{$comparePrice}</div>" : '';

        $addToCartButton = $stock > 0
            ? '<button type="submit" class="btn-add-cart">Add to Cart</button>'
            : '<button type="button" class="btn-disabled" disabled>Out of Stock</button>';

        // Related products
        $relatedHtml = '';
        foreach ($relatedProducts as $rel) {
            $relPrice = $this->productService->formatPrice($rel['price']);
            $relImage = $rel['primary_image'] ?? '/assets/images/placeholder.jpg';
            $relSlug = htmlspecialchars($rel['slug']);
            $relName = htmlspecialchars($rel['name']);

            $relatedHtml .= <<<HTML
<div class="related-product">
    <a href="/products/{$relSlug}">
        <img src="{$relImage}" alt="{$relName}">
        <div class="related-info">
            <div class="related-name">{$relName}</div>
            <div class="related-price">{$relPrice}</div>
        </div>
    </a>
</div>
HTML;
        }

        $user = SessionHelper::getUser();
        $authLinks = $user
            ? '<a href="/account">Account</a><form method="POST" action="/logout" style="display:inline;"><button type="submit" class="link-button">Logout</button></form>'
            : '<a href="/login">Login</a><a href="/register">Register</a>';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$name} | E-Commerce</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; background: #f7fafc; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header-content { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 24px; font-weight: bold; text-decoration: none; color: white; }
        .nav { display: flex; gap: 20px; align-items: center; }
        .nav a, .link-button { color: white; text-decoration: none; transition: opacity 0.2s; background: none; border: none; color: white; cursor: pointer; font-size: 16px; }
        .nav a:hover, .link-button:hover { opacity: 0.8; }
        .container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        .breadcrumb { margin-bottom: 20px; color: #666; }
        .breadcrumb a { color: #667eea; text-decoration: none; }
        .product-detail { background: white; border-radius: 12px; padding: 40px; margin-bottom: 40px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); display: grid; grid-template-columns: 1fr 1fr; gap: 60px; }
        .product-image-container { position: relative; }
        .product-image { width: 100%; border-radius: 8px; overflow: hidden; }
        .product-image img { width: 100%; display: block; }
        .badge { display: inline-block; background: #48bb78; color: white; padding: 8px 16px; border-radius: 6px; font-size: 14px; font-weight: 600; margin-bottom: 15px; }
        .product-title { font-size: 32px; margin-bottom: 10px; }
        .product-meta { display: flex; gap: 20px; margin-bottom: 20px; color: #666; font-size: 14px; }
        .product-price-section { margin: 30px 0; }
        .compare-price { color: #999; text-decoration: line-through; font-size: 20px; margin-bottom: 5px; }
        .product-price { color: #667eea; font-size: 40px; font-weight: bold; }
        .stock { display: inline-block; padding: 8px 16px; border-radius: 6px; font-size: 14px; margin: 20px 0; }
        .stock.in-stock { background: #c6f6d5; color: #22543d; }
        .stock.out-of-stock { background: #fed7d7; color: #742a2a; }
        .product-description { margin: 30px 0; line-height: 1.8; color: #555; }
        .btn-add-cart { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 16px 32px; border: none; border-radius: 8px; font-size: 18px; font-weight: 600; cursor: pointer; width: 100%; transition: transform 0.2s; }
        .btn-add-cart:hover { transform: translateY(-2px); }
        .btn-disabled { background: #cbd5e0; color: #718096; padding: 16px 32px; border: none; border-radius: 8px; font-size: 18px; width: 100%; cursor: not-allowed; }
        .related-section { background: white; border-radius: 12px; padding: 40px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .related-section h2 { margin-bottom: 30px; }
        .related-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; }
        .related-product a { text-decoration: none; color: inherit; display: block; border-radius: 8px; overflow: hidden; transition: transform 0.2s; }
        .related-product a:hover { transform: translateY(-5px); }
        .related-product img { width: 100%; aspect-ratio: 1; object-fit: cover; }
        .related-info { padding: 15px; }
        .related-name { font-weight: 600; margin-bottom: 5px; }
        .related-price { color: #667eea; font-weight: bold; }
        @media (max-width: 768px) {
            .product-detail { grid-template-columns: 1fr; gap: 30px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <a href="/" class="logo">🛍️ E-Commerce</a>
            <div class="nav">
                <a href="/products">Products</a>
                <a href="/cart">🛒 Cart</a>
                {$authLinks}
            </div>
        </div>
    </div>

    <div class="container">
        <div class="breadcrumb">
            <a href="/">Home</a> / <a href="/products">Products</a> / <a href="/categories/{$product['category_slug']}">{$category}</a> / {$name}
        </div>

        <div class="product-detail">
            <div class="product-image-container">
                <div class="product-image">
                    <img src="{$imageUrl}" alt="{$name}">
                </div>
            </div>

            <div class="product-info">
                {$discountBadge}
                <h1 class="product-title">{$name}</h1>

                <div class="product-meta">
                    <span>SKU: {$sku}</span>
                    <span>Category: {$category}</span>
                </div>

                <div class="product-price-section">
                    {$comparePriceHtml}
                    <div class="product-price">{$price}</div>
                </div>

                {$stockStatus}

                <div class="product-description">
                    {$description}
                </div>

                <form action="/cart/add" method="POST">
                    <input type="hidden" name="product_id" value="{$product['id']}">
                    <input type="hidden" name="quantity" value="1">
                    {$addToCartButton}
                </form>
            </div>
        </div>

        <div class="related-section">
            <h2>Related Products</h2>
            <div class="related-grid">
                {$relatedHtml}
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
