<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

interface ReviewRepositoryInterface
{
    /**
     * Create a new review
     */
    public function create(array $data): array;

    /**
     * Find review by ID
     */
    public function find(int $id): ?array;

    /**
     * Find review by UUID
     */
    public function findByUuid(string $uuid): ?array;

    /**
     * Get reviews for a product
     */
    public function getByProduct(int $productId, int $limit = 10, int $offset = 0): array;

    /**
     * Get reviews by a user
     */
    public function getByUser(int $userId, int $limit = 10, int $offset = 0): array;

    /**
     * Check if user has already reviewed a product
     */
    public function hasUserReviewed(int $productId, int $userId): bool;

    /**
     * Check if user purchased the product
     */
    public function isVerifiedPurchase(int $productId, int $userId): bool;

    /**
     * Update review
     */
    public function update(int $id, array $data): bool;

    /**
     * Delete review
     */
    public function delete(int $id): bool;

    /**
     * Mark review as helpful
     */
    public function markHelpful(int $reviewId, int $userId): bool;

    /**
     * Unmark review as helpful
     */
    public function unmarkHelpful(int $reviewId, int $userId): bool;

    /**
     * Check if user marked review as helpful
     */
    public function hasMarkedHelpful(int $reviewId, int $userId): bool;

    /**
     * Get pending reviews for moderation
     */
    public function getPendingReviews(int $limit = 20, int $offset = 0): array;

    /**
     * Approve review
     */
    public function approve(int $id): bool;

    /**
     * Reject review
     */
    public function reject(int $id): bool;
}
