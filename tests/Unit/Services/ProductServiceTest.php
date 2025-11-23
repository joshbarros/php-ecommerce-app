<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ProductService;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use Mockery;

final class ProductServiceTest extends TestCase
{
    private ProductRepositoryInterface $productRepository;
    private LoggerInterface $logger;
    private ProductService $productService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $this->logger = Mockery::mock(LoggerInterface::class);
        $this->productService = new ProductService($this->productRepository, $this->logger);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetAllProductsReturnsProducts(): void
    {
        $expectedProducts = [
            ['id' => 1, 'name' => 'Product 1', 'price' => 19.99],
            ['id' => 2, 'name' => 'Product 2', 'price' => 29.99],
        ];

        $this->productRepository
            ->shouldReceive('findAll')
            ->with(20, 0)
            ->once()
            ->andReturn($expectedProducts);

        $products = $this->productService->getAllProducts(20, 0);

        $this->assertEquals($expectedProducts, $products);
    }

    public function testGetProductBySlugReturnsProduct(): void
    {
        $expectedProduct = [
            'id' => 1,
            'slug' => 'test-product',
            'name' => 'Test Product',
            'price' => 19.99
        ];

        $this->productRepository
            ->shouldReceive('findBySlug')
            ->with('test-product')
            ->once()
            ->andReturn($expectedProduct);

        $product = $this->productService->getProductBySlug('test-product');

        $this->assertEquals($expectedProduct, $product);
    }

    public function testGetProductBySlugReturnsNullWhenNotFound(): void
    {
        $this->productRepository
            ->shouldReceive('findBySlug')
            ->with('nonexistent')
            ->once()
            ->andReturn(null);

        $product = $this->productService->getProductBySlug('nonexistent');

        $this->assertNull($product);
    }

    public function testSearchProductsReturnsResults(): void
    {
        $expectedResults = [
            ['id' => 1, 'name' => 'Gaming Laptop', 'price' => 999.99],
            ['id' => 2, 'name' => 'Gaming Mouse', 'price' => 49.99],
        ];

        $this->productRepository
            ->shouldReceive('search')
            ->with('gaming', 20, 0)
            ->once()
            ->andReturn($expectedResults);

        $results = $this->productService->searchProducts('gaming', 20, 0);

        $this->assertEquals($expectedResults, $results);
    }

    public function testGetProductsByCategoryReturnsProducts(): void
    {
        $expectedProducts = [
            ['id' => 1, 'name' => 'Product 1', 'category_id' => 5],
            ['id' => 2, 'name' => 'Product 2', 'category_id' => 5],
        ];

        $this->productRepository
            ->shouldReceive('findByCategory')
            ->with(5, 20, 0)
            ->once()
            ->andReturn($expectedProducts);

        $products = $this->productService->getProductsByCategory(5, 20, 0);

        $this->assertEquals($expectedProducts, $products);
    }

    public function testCheckStockAvailabilityReturnsTrueWhenInStock(): void
    {
        $this->productRepository
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn(['id' => 1, 'stock_quantity' => 10]);

        $isAvailable = $this->productService->checkStockAvailability(1, 5);

        $this->assertTrue($isAvailable);
    }

    public function testCheckStockAvailabilityReturnsFalseWhenOutOfStock(): void
    {
        $this->productRepository
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn(['id' => 1, 'stock_quantity' => 3]);

        $isAvailable = $this->productService->checkStockAvailability(1, 5);

        $this->assertFalse($isAvailable);
    }

    public function testCheckStockAvailabilityReturnsFalseWhenProductNotFound(): void
    {
        $this->productRepository
            ->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $isAvailable = $this->productService->checkStockAvailability(999, 1);

        $this->assertFalse($isAvailable);
    }

    public function testGetFeaturedProductsReturnsLimitedProducts(): void
    {
        $expectedProducts = [
            ['id' => 1, 'name' => 'Featured 1', 'is_featured' => true],
            ['id' => 2, 'name' => 'Featured 2', 'is_featured' => true],
        ];

        $this->productRepository
            ->shouldReceive('findFeatured')
            ->with(10)
            ->once()
            ->andReturn($expectedProducts);

        $products = $this->productService->getFeaturedProducts(10);

        $this->assertEquals($expectedProducts, $products);
    }
}
