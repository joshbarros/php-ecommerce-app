<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Repositories\Interfaces\CartRepositoryInterface;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use Psr\Log\LoggerInterface;

final class CartService
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Get or create cart for current session/user
     */
    public function getOrCreateCart(?int $userId, string $sessionId): array
    {
        // Try to find existing cart
        if ($userId !== null) {
            $cart = $this->cartRepository->findByUserId($userId);
        } else {
            $cart = $this->cartRepository->findBySessionId($sessionId);
        }

        // Create new cart if not found
        if (!$cart) {
            $cartId = $this->cartRepository->create([
                'user_id' => $userId,
                'session_id' => $userId === null ? $sessionId : null
            ]);

            $cart = [
                'id' => $cartId,
                'user_id' => $userId,
                'session_id' => $userId === null ? $sessionId : null
            ];
        }

        return $cart;
    }

    /**
     * Get cart with items
     */
    public function getCart(?int $userId, string $sessionId): array
    {
        $cart = $this->getOrCreateCart($userId, $sessionId);
        $items = $this->cartRepository->getItems($cart['id']);

        // Calculate totals
        $subtotal = 0;
        $itemCount = 0;
        $validItems = [];

        foreach ($items as $item) {
            // Check if product is still active and in stock
            if (!$item['product_is_active']) {
                $this->logger->warning('Inactive product in cart', [
                    'product_id' => $item['product_id'],
                    'cart_id' => $cart['id']
                ]);
                continue;
            }

            if ($item['stock_quantity'] < $item['quantity']) {
                // Update quantity to available stock
                $item['quantity'] = $item['stock_quantity'];
                if ($item['quantity'] > 0) {
                    $this->cartRepository->updateItemQuantity(
                        $cart['id'],
                        $item['product_id'],
                        $item['quantity']
                    );
                } else {
                    $this->cartRepository->removeItem($cart['id'], $item['product_id']);
                    continue;
                }
            }

            $item['line_total'] = $item['quantity'] * $item['price'];
            $subtotal += $item['line_total'];
            $itemCount += $item['quantity'];
            $validItems[] = $item;
        }

        return [
            'cart' => $cart,
            'items' => $validItems,
            'subtotal' => $subtotal,
            'item_count' => $itemCount
        ];
    }

    /**
     * Add item to cart
     */
    public function addItem(?int $userId, string $sessionId, int $productId, int $quantity = 1): array
    {
        if ($quantity <= 0) {
            throw new ValidationException('Quantity must be greater than 0');
        }

        // Get product
        $product = $this->productRepository->findById($productId);

        if (!$product) {
            throw new ValidationException('Product not found');
        }

        if (!$product['is_active']) {
            throw new ValidationException('Product is not available');
        }

        // Check stock
        if ($product['stock_quantity'] < $quantity) {
            throw new ValidationException(
                'Not enough stock available. Only ' . $product['stock_quantity'] . ' items in stock.'
            );
        }

        // Get or create cart
        $cart = $this->getOrCreateCart($userId, $sessionId);

        // Get current cart items to check if product already exists
        $items = $this->cartRepository->getItems($cart['id']);
        $existingQuantity = 0;

        foreach ($items as $item) {
            if ($item['product_id'] == $productId) {
                $existingQuantity = $item['quantity'];
                break;
            }
        }

        // Check if total quantity exceeds stock
        if (($existingQuantity + $quantity) > $product['stock_quantity']) {
            throw new ValidationException(
                'Cannot add ' . $quantity . ' items. Maximum available: ' .
                ($product['stock_quantity'] - $existingQuantity)
            );
        }

        // Add item
        $this->cartRepository->addItem($cart['id'], $productId, $quantity, $product['price']);

        $this->logger->info('Item added to cart', [
            'cart_id' => $cart['id'],
            'product_id' => $productId,
            'quantity' => $quantity
        ]);

        return $this->getCart($userId, $sessionId);
    }

    /**
     * Update cart item quantity
     */
    public function updateItemQuantity(?int $userId, string $sessionId, int $productId, int $quantity): array
    {
        if ($quantity < 0) {
            throw new ValidationException('Quantity cannot be negative');
        }

        $cart = $this->getOrCreateCart($userId, $sessionId);

        if ($quantity === 0) {
            $this->cartRepository->removeItem($cart['id'], $productId);

            $this->logger->info('Item removed from cart', [
                'cart_id' => $cart['id'],
                'product_id' => $productId
            ]);
        } else {
            // Check stock
            $product = $this->productRepository->findById($productId);

            if (!$product) {
                throw new ValidationException('Product not found');
            }

            if ($product['stock_quantity'] < $quantity) {
                throw new ValidationException(
                    'Not enough stock available. Only ' . $product['stock_quantity'] . ' items in stock.'
                );
            }

            $this->cartRepository->updateItemQuantity($cart['id'], $productId, $quantity);

            $this->logger->info('Cart item quantity updated', [
                'cart_id' => $cart['id'],
                'product_id' => $productId,
                'quantity' => $quantity
            ]);
        }

        return $this->getCart($userId, $sessionId);
    }

    /**
     * Remove item from cart
     */
    public function removeItem(?int $userId, string $sessionId, int $productId): array
    {
        $cart = $this->getOrCreateCart($userId, $sessionId);
        $this->cartRepository->removeItem($cart['id'], $productId);

        $this->logger->info('Item removed from cart', [
            'cart_id' => $cart['id'],
            'product_id' => $productId
        ]);

        return $this->getCart($userId, $sessionId);
    }

    /**
     * Clear all items from cart
     */
    public function clearCart(?int $userId, string $sessionId): array
    {
        $cart = $this->getOrCreateCart($userId, $sessionId);
        $this->cartRepository->clearItems($cart['id']);

        $this->logger->info('Cart cleared', ['cart_id' => $cart['id']]);

        return $this->getCart($userId, $sessionId);
    }

    /**
     * Merge guest cart into user cart on login
     */
    public function mergeGuestCart(string $guestSessionId, int $userId): void
    {
        $guestCart = $this->cartRepository->findBySessionId($guestSessionId);

        if (!$guestCart) {
            return; // No guest cart to merge
        }

        // Get or create user cart
        $userCart = $this->getOrCreateCart($userId, '');

        // Merge carts
        $this->cartRepository->mergeCart($guestCart['id'], $userCart['id']);

        $this->logger->info('Guest cart merged into user cart', [
            'guest_cart_id' => $guestCart['id'],
            'user_cart_id' => $userCart['id'],
            'user_id' => $userId
        ]);
    }

    /**
     * Get cart item count for current session/user
     */
    public function getItemCount(?int $userId, string $sessionId): int
    {
        try {
            $cart = $this->getOrCreateCart($userId, $sessionId);
            return $this->cartRepository->getCartItemCount($cart['id']);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get cart item count', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Validate cart before checkout
     */
    public function validateCart(?int $userId, string $sessionId): array
    {
        $cartData = $this->getCart($userId, $sessionId);
        $errors = [];

        if (empty($cartData['items'])) {
            $errors[] = 'Cart is empty';
        }

        foreach ($cartData['items'] as $item) {
            // Re-check stock availability
            $product = $this->productRepository->findById($item['product_id']);

            if (!$product || !$product['is_active']) {
                $errors[] = $item['product_name'] . ' is no longer available';
            } elseif ($product['stock_quantity'] < $item['quantity']) {
                $errors[] = $item['product_name'] . ' only has ' . $product['stock_quantity'] . ' items in stock';
            }

            // Check for price changes
            if ($product && abs($product['price'] - $item['price']) > 0.01) {
                $errors[] = 'Price for ' . $item['product_name'] . ' has changed from $' .
                    number_format($item['price'], 2) . ' to $' . number_format($product['price'], 2);
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'cart' => $cartData
        ];
    }
}
