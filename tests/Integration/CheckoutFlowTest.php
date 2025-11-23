<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\ProductService;
use App\Repositories\CartRepository;
use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the complete checkout flow
 * Tests the interaction between Cart, Product, and Checkout services
 */
final class CheckoutFlowTest extends TestCase
{
    private PDO $pdo;
    private CartService $cartService;
    private CheckoutService $checkoutService;
    private ProductService $productService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test database connection
        $this->pdo = createTestDatabase();

        // Clean database before each test
        cleanupTestDatabase($this->pdo);

        // Create a mock logger
        $logger = new class {
            public function info(string $message, array $context = []): void {}
            public function warning(string $message, array $context = []): void {}
            public function error(string $message, array $context = []): void {}
        };

        // Initialize repositories
        $cartRepository = new CartRepository($this->pdo);
        $productRepository = new ProductRepository($this->pdo);
        $orderRepository = new OrderRepository($this->pdo);

        // Initialize services
        $this->cartService = new CartService($cartRepository, $productRepository, $logger);
        $this->productService = new ProductService($productRepository, $logger);
        $this->checkoutService = new CheckoutService(
            $orderRepository,
            $cartRepository,
            $productRepository,
            $logger
        );

        // Clear session
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        cleanupTestDatabase($this->pdo);
        parent::tearDown();
    }

    public function testCompleteCheckoutFlowFromCartToOrder(): void
    {
        // Step 1: Create test products
        $product1 = $this->createTestProduct([
            'sku' => 'TEST-001',
            'name' => 'Test Product 1',
            'slug' => 'test-product-1',
            'price' => 19.99,
            'stock_quantity' => 100
        ]);

        $product2 = $this->createTestProduct([
            'sku' => 'TEST-002',
            'name' => 'Test Product 2',
            'slug' => 'test-product-2',
            'price' => 29.99,
            'stock_quantity' => 50
        ]);

        // Step 2: Add products to cart
        $this->cartService->addToCart($product1['id'], 2);
        $this->cartService->addToCart($product2['id'], 1);

        // Step 3: Verify cart contents
        $cartItems = $this->cartService->getCartItems();
        $this->assertCount(2, $cartItems);

        $cartTotal = $this->cartService->getCartTotal();
        $expectedTotal = (19.99 * 2) + (29.99 * 1);
        $this->assertEquals($expectedTotal, $cartTotal);

        // Step 4: Create order from cart
        $orderData = [
            'shipping_name' => 'John Doe',
            'shipping_email' => 'john@example.com',
            'shipping_phone' => '555-1234',
            'shipping_address' => '123 Main St',
            'shipping_city' => 'San Francisco',
            'shipping_state' => 'CA',
            'shipping_zip' => '94102',
            'shipping_country' => 'US'
        ];

        $order = $this->checkoutService->createOrder($orderData);

        // Step 5: Verify order was created
        $this->assertNotNull($order);
        $this->assertArrayHasKey('id', $order);
        $this->assertArrayHasKey('uuid', $order);
        $this->assertEquals('pending', $order['status']);
        $this->assertEquals('John Doe', $order['shipping_name']);

        // Step 6: Verify order items
        $orderItems = $this->checkoutService->getOrderItems($order['id']);
        $this->assertCount(2, $orderItems);

        // Step 7: Verify stock was reduced
        $updatedProduct1 = $this->productService->getProductById($product1['id']);
        $this->assertEquals(98, $updatedProduct1['stock_quantity']);

        $updatedProduct2 = $this->productService->getProductById($product2['id']);
        $this->assertEquals(49, $updatedProduct2['stock_quantity']);

        // Step 8: Verify cart was cleared
        $cartItemsAfter = $this->cartService->getCartItems();
        $this->assertEmpty($cartItemsAfter);
    }

    public function testCheckoutFailsWithInsufficientStock(): void
    {
        // Create product with limited stock
        $product = $this->createTestProduct([
            'sku' => 'TEST-003',
            'name' => 'Limited Stock Product',
            'slug' => 'limited-stock',
            'price' => 49.99,
            'stock_quantity' => 2
        ]);

        // Try to add more than available stock
        $this->expectException(\App\Exceptions\CartException::class);
        $this->expectExceptionMessage('Insufficient stock');

        $this->cartService->addToCart($product['id'], 5);
    }

    public function testOrderTotalIncludesTaxAndShipping(): void
    {
        // Create test product
        $product = $this->createTestProduct([
            'sku' => 'TEST-004',
            'name' => 'Tax Test Product',
            'slug' => 'tax-test',
            'price' => 100.00,
            'stock_quantity' => 10
        ]);

        // Add to cart
        $this->cartService->addToCart($product['id'], 1);

        // Create order with CA address (7.25% tax)
        $orderData = [
            'shipping_name' => 'Jane Doe',
            'shipping_email' => 'jane@example.com',
            'shipping_phone' => '555-5678',
            'shipping_address' => '456 Oak Ave',
            'shipping_city' => 'Los Angeles',
            'shipping_state' => 'CA',
            'shipping_zip' => '90001',
            'shipping_country' => 'US'
        ];

        $order = $this->checkoutService->createOrder($orderData);

        // Verify subtotal
        $this->assertEquals(100.00, $order['subtotal']);

        // Verify tax (7.25% of $100)
        $this->assertEquals(7.25, $order['tax']);

        // Verify shipping
        $this->assertGreaterThan(0, $order['shipping']);

        // Verify total
        $expectedTotal = 100.00 + 7.25 + $order['shipping'];
        $this->assertEquals($expectedTotal, $order['total']);
    }

    public function testMultipleProductsCheckout(): void
    {
        // Create multiple products
        for ($i = 1; $i <= 5; $i++) {
            $product = $this->createTestProduct([
                'sku' => "MULTI-{$i}",
                'name' => "Multi Product {$i}",
                'slug' => "multi-product-{$i}",
                'price' => 10.00 * $i,
                'stock_quantity' => 100
            ]);

            $this->cartService->addToCart($product['id'], $i);
        }

        // Verify cart has all items
        $cartItems = $this->cartService->getCartItems();
        $this->assertCount(5, $cartItems);

        // Calculate expected total
        // (10*1) + (20*2) + (30*3) + (40*4) + (50*5) = 10 + 40 + 90 + 160 + 250 = 550
        $cartTotal = $this->cartService->getCartTotal();
        $this->assertEquals(550.00, $cartTotal);

        // Complete checkout
        $orderData = [
            'shipping_name' => 'Multi Test',
            'shipping_email' => 'multi@example.com',
            'shipping_phone' => '555-9999',
            'shipping_address' => '789 Pine St',
            'shipping_city' => 'Seattle',
            'shipping_state' => 'WA',
            'shipping_zip' => '98101',
            'shipping_country' => 'US'
        ];

        $order = $this->checkoutService->createOrder($orderData);

        // Verify order items
        $orderItems = $this->checkoutService->getOrderItems($order['id']);
        $this->assertCount(5, $orderItems);

        // Verify all items are in the order
        $totalQuantity = array_sum(array_column($orderItems, 'quantity'));
        $this->assertEquals(15, $totalQuantity); // 1+2+3+4+5
    }

    /**
     * Helper method to create a test product
     */
    private function createTestProduct(array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO products (sku, name, slug, price, stock_quantity, category_id, is_active)
             VALUES (:sku, :name, :slug, :price, :stock_quantity, 1, true)
             RETURNING id, uuid, sku, name, slug, price, stock_quantity'
        );

        $stmt->execute($data);

        return $stmt->fetch();
    }
}
