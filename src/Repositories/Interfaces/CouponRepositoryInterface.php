<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

interface CouponRepositoryInterface
{
    /**
     * Find coupon by code
     */
    public function findByCode(string $code): ?array;

    /**
     * Find coupon by ID
     */
    public function find(int $id): ?array;

    /**
     * Create a new coupon
     */
    public function create(array $data): array;

    /**
     * Update coupon
     */
    public function update(int $id, array $data): bool;

    /**
     * Delete coupon
     */
    public function delete(int $id): bool;

    /**
     * Get all coupons with pagination
     */
    public function getAll(int $limit = 20, int $offset = 0): array;

    /**
     * Get active coupons
     */
    public function getActive(int $limit = 20, int $offset = 0): array;

    /**
     * Record coupon usage
     */
    public function recordUsage(int $couponId, int $orderId, ?int $userId, float $discountAmount): bool;

    /**
     * Get coupon usage count for a user
     */
    public function getUserUsageCount(int $couponId, int $userId): int;

    /**
     * Check if user has placed an order before
     */
    public function hasUserPlacedOrder(int $userId): bool;

    /**
     * Get usage statistics for a coupon
     */
    public function getUsageStats(int $couponId): array;
}
