<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\CartService;
use App\Repositories\Interfaces\CartRepositoryInterface;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use App\Exceptions\CartException;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use Mockery;

final class CartServiceTest extends TestCase
{
    private CartRepositoryInterface $cartRepository;
    private ProductRepositoryInterface $productRepository;
    private LoggerInterface $logger;
    private CartService $cartService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cartRepository = Mockery::mock(CartRepositoryInterface::class);
        $this->productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $this->logger = Mockery::mock(LoggerInterface::class);

        $this->cartService = new CartService(
            $this->cartRepository,
            $this->productRepository,
            $this->logger
        );

        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testAddToCartSucceedsWithSufficientStock(): void
    {
        $productId = 1;
        $quantity = 2;

        $product = [
            'id' => $productId,
            'name' => 'Test Product',
            'price' => 19.99,
            'stock_quantity' => 10
        ];

        $this->productRepository
            ->shouldReceive('find')
            ->with($productId)
            ->once()
            ->andReturn($product);

        $this->cartRepository
            ->shouldReceive('addItem')
            ->once()
            ->andReturn(true);

        $this->logger
            ->shouldReceive('info')
            ->once();

        $result = $this->cartService->addToCart($productId, $quantity);

        $this->assertTrue($result);
    }

    public function testAddToCartThrowsExceptionWhenProductNotFound(): void
    {
        $this->productRepository
            ->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $this->logger
            ->shouldReceive('warning')
            ->once();

        $this->expectException(CartException::class);
        $this->expectExceptionMessage('Product not found');

        $this->cartService->addToCart(999, 1);
    }

    public function testAddToCartThrowsExceptionWhenInsufficientStock(): void
    {
        $product = [
            'id' => 1,
            'name' => 'Test Product',
            'stock_quantity' => 2
        ];

        $this->productRepository
            ->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($product);

        $this->logger
            ->shouldReceive('warning')
            ->once();

        $this->expectException(CartException::class);
        $this->expectExceptionMessage('Insufficient stock');

        $this->cartService->addToCart(1, 5);
    }

    public function testUpdateQuantitySucceeds(): void
    {
        $cartItemId = 1;
        $newQuantity = 3;

        $cartItem = [
            'id' => $cartItemId,
            'product_id' => 10,
            'quantity' => 1
        ];

        $product = [
            'id' => 10,
            'stock_quantity' => 10
        ];

        $this->cartRepository
            ->shouldReceive('getItem')
            ->with($cartItemId)
            ->once()
            ->andReturn($cartItem);

        $this->productRepository
            ->shouldReceive('find')
            ->with(10)
            ->once()
            ->andReturn($product);

        $this->cartRepository
            ->shouldReceive('updateQuantity')
            ->with($cartItemId, $newQuantity)
            ->once()
            ->andReturn(true);

        $this->logger
            ->shouldReceive('info')
            ->once();

        $result = $this->cartService->updateQuantity($cartItemId, $newQuantity);

        $this->assertTrue($result);
    }

    public function testUpdateQuantityThrowsExceptionWhenInsufficientStock(): void
    {
        $cartItem = [
            'id' => 1,
            'product_id' => 10,
            'quantity' => 1
        ];

        $product = [
            'id' => 10,
            'stock_quantity' => 2
        ];

        $this->cartRepository
            ->shouldReceive('getItem')
            ->with(1)
            ->once()
            ->andReturn($cartItem);

        $this->productRepository
            ->shouldReceive('find')
            ->with(10)
            ->once()
            ->andReturn($product);

        $this->logger
            ->shouldReceive('warning')
            ->once();

        $this->expectException(CartException::class);
        $this->expectExceptionMessage('Insufficient stock');

        $this->cartService->updateQuantity(1, 5);
    }

    public function testRemoveItemSucceeds(): void
    {
        $this->cartRepository
            ->shouldReceive('removeItem')
            ->with(1)
            ->once()
            ->andReturn(true);

        $this->logger
            ->shouldReceive('info')
            ->once();

        $result = $this->cartService->removeItem(1);

        $this->assertTrue($result);
    }

    public function testClearCartSucceeds(): void
    {
        $_SESSION['cart_id'] = 'test-cart-id';

        $this->cartRepository
            ->shouldReceive('clear')
            ->with('test-cart-id')
            ->once()
            ->andReturn(true);

        $this->logger
            ->shouldReceive('info')
            ->once();

        $result = $this->cartService->clearCart();

        $this->assertTrue($result);
    }

    public function testGetCartItemsReturnsItems(): void
    {
        $_SESSION['cart_id'] = 'test-cart-id';

        $expectedItems = [
            ['id' => 1, 'product_id' => 10, 'quantity' => 2],
            ['id' => 2, 'product_id' => 11, 'quantity' => 1],
        ];

        $this->cartRepository
            ->shouldReceive('getItems')
            ->with('test-cart-id')
            ->once()
            ->andReturn($expectedItems);

        $items = $this->cartService->getCartItems();

        $this->assertEquals($expectedItems, $items);
    }

    public function testGetCartTotalCalculatesCorrectTotal(): void
    {
        $_SESSION['cart_id'] = 'test-cart-id';

        $items = [
            ['product_id' => 1, 'quantity' => 2, 'price' => 10.00],
            ['product_id' => 2, 'quantity' => 1, 'price' => 15.00],
        ];

        $this->cartRepository
            ->shouldReceive('getItems')
            ->with('test-cart-id')
            ->once()
            ->andReturn($items);

        $total = $this->cartService->getCartTotal();

        $this->assertEquals(35.00, $total);
    }

    public function testGetCartCountReturnsItemCount(): void
    {
        $_SESSION['cart_id'] = 'test-cart-id';

        $this->cartRepository
            ->shouldReceive('getItemCount')
            ->with('test-cart-id')
            ->once()
            ->andReturn(5);

        $count = $this->cartService->getCartCount();

        $this->assertEquals(5, $count);
    }
}
