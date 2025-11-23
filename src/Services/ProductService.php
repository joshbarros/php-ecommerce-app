<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NotFoundException;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use Psr\Log\LoggerInterface;

final class ProductService
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Get product by ID
     */
    public function getProductById(int $id): array
    {
        $product = $this->productRepository->findById($id);

        if (!$product) {
            throw new NotFoundException("Product not found");
        }

        return $product;
    }

    /**
     * Get product by slug
     */
    public function getProductBySlug(string $slug): array
    {
        $product = $this->productRepository->findBySlug($slug);

        if (!$product) {
            throw new NotFoundException("Product not found");
        }

        return $product;
    }

    /**
     * Get all products with pagination
     */
    public function getAllProducts(int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $products = $this->productRepository->findAll($perPage, $offset);
        $total = $this->productRepository->countAll();

        return [
            'products' => $products,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => (int) ceil($total / $perPage),
            'hasMore' => ($page * $perPage) < $total
        ];
    }

    /**
     * Get products by category
     */
    public function getProductsByCategory(int $categoryId, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $products = $this->productRepository->findByCategory($categoryId, $perPage, $offset);
        $total = $this->productRepository->countByCategory($categoryId);

        return [
            'products' => $products,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => (int) ceil($total / $perPage),
            'hasMore' => ($page * $perPage) < $total
        ];
    }

    /**
     * Get featured products
     */
    public function getFeaturedProducts(int $limit = 10): array
    {
        return $this->productRepository->findFeatured($limit);
    }

    /**
     * Search products
     */
    public function searchProducts(string $query, int $page = 1, int $perPage = 20): array
    {
        if (empty(trim($query))) {
            return $this->getAllProducts($page, $perPage);
        }

        $offset = ($page - 1) * $perPage;
        $products = $this->productRepository->search($query, $perPage, $offset);
        $total = $this->productRepository->countSearch($query);

        $this->logger->info('Product search executed', [
            'query' => $query,
            'results' => count($products)
        ]);

        return [
            'products' => $products,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => (int) ceil($total / $perPage),
            'hasMore' => ($page * $perPage) < $total,
            'query' => $query
        ];
    }

    /**
     * Check if product is in stock
     */
    public function isInStock(int $productId, int $quantity = 1): bool
    {
        $product = $this->productRepository->findById($productId);

        if (!$product) {
            return false;
        }

        return $product['stock_quantity'] >= $quantity;
    }

    /**
     * Format price for display
     */
    public function formatPrice(float $price): string
    {
        return '$' . number_format($price, 2);
    }

    /**
     * Calculate discount percentage
     */
    public function getDiscountPercentage(float $price, float $comparePrice): ?int
    {
        if ($comparePrice <= $price) {
            return null;
        }

        return (int) round((($comparePrice - $price) / $comparePrice) * 100);
    }
}
