<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

interface OrderRepositoryInterface
{
    /**
     * Find order by ID
     */
    public function findById(int $id): ?array;

    /**
     * Find order by UUID
     */
    public function findByUuid(string $uuid): ?array;

    /**
     * Find orders by user ID
     */
    public function findByUserId(int $userId, int $limit = 10, int $offset = 0): array;

    /**
     * Get order items
     */
    public function getOrderItems(int $orderId): array;

    /**
     * Create a new order
     */
    public function create(array $data): int;

    /**
     * Add item to order
     */
    public function addItem(int $orderId, array $itemData): int;

    /**
     * Update order status
     */
    public function updateStatus(int $id, string $status): bool;

    /**
     * Update order
     */
    public function update(int $id, array $data): bool;

    /**
     * Get total order count for user
     */
    public function countByUserId(int $userId): int;

    /**
     * Get recent orders
     */
    public function getRecentOrders(int $limit = 10): array;

    /**
     * Get orders by status
     */
    public function findByStatus(string $status, int $limit = 50, int $offset = 0): array;
}
