<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

interface CartRepositoryInterface
{
    /**
     * Find cart by user ID
     */
    public function findByUserId(int $userId): ?array;

    /**
     * Find cart by session ID
     */
    public function findBySessionId(string $sessionId): ?array;

    /**
     * Create a new cart
     */
    public function create(array $data): int;

    /**
     * Update cart
     */
    public function update(int $id, array $data): bool;

    /**
     * Delete cart
     */
    public function delete(int $id): bool;

    /**
     * Get cart items
     */
    public function getItems(int $cartId): array;

    /**
     * Add item to cart
     */
    public function addItem(int $cartId, int $productId, int $quantity, float $price): int;

    /**
     * Update cart item quantity
     */
    public function updateItemQuantity(int $cartId, int $productId, int $quantity): bool;

    /**
     * Remove item from cart
     */
    public function removeItem(int $cartId, int $productId): bool;

    /**
     * Clear all items from cart
     */
    public function clearItems(int $cartId): bool;

    /**
     * Get cart total
     */
    public function getCartTotal(int $cartId): float;

    /**
     * Get cart item count
     */
    public function getCartItemCount(int $cartId): int;

    /**
     * Merge guest cart into user cart
     */
    public function mergeCart(int $guestCartId, int $userCartId): bool;

    /**
     * Delete old carts (cleanup)
     */
    public function deleteOldCarts(int $daysOld = 30): int;
}
