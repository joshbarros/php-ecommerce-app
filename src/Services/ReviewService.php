<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\Interfaces\ReviewRepositoryInterface;
use App\Exceptions\ReviewException;
use Psr\Log\LoggerInterface;

final class ReviewService
{
    public function __construct(
        private readonly ReviewRepositoryInterface $reviewRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Create a product review
     */
    public function createReview(array $data): array
    {
        // Validate rating
        if (!isset($data['rating']) || $data['rating'] < 1 || $data['rating'] > 5) {
            throw new ReviewException('Rating must be between 1 and 5');
        }

        // Check if user already reviewed this product
        if ($this->reviewRepository->hasUserReviewed($data['product_id'], $data['user_id'])) {
            throw new ReviewException('You have already reviewed this product');
        }

        // Check if verified purchase
        $data['is_verified_purchase'] = $this->reviewRepository->isVerifiedPurchase(
            $data['product_id'],
            $data['user_id']
        );

        $review = $this->reviewRepository->create($data);

        $this->logger->info('Product review created', [
            'review_id' => $review['id'],
            'product_id' => $data['product_id'],
            'user_id' => $data['user_id'],
            'rating' => $data['rating']
        ]);

        return $review;
    }

    /**
     * Update a review
     */
    public function updateReview(int $reviewId, int $userId, array $data): bool
    {
        $review = $this->reviewRepository->find($reviewId);

        if (!$review) {
            throw new ReviewException('Review not found');
        }

        // Verify ownership
        if ($review['user_id'] !== $userId) {
            throw new ReviewException('You can only edit your own reviews');
        }

        // Validate rating if provided
        if (isset($data['rating']) && ($data['rating'] < 1 || $data['rating'] > 5)) {
            throw new ReviewException('Rating must be between 1 and 5');
        }

        $result = $this->reviewRepository->update($reviewId, $data);

        $this->logger->info('Product review updated', [
            'review_id' => $reviewId,
            'user_id' => $userId
        ]);

        return $result;
    }

    /**
     * Delete a review
     */
    public function deleteReview(int $reviewId, int $userId, bool $isAdmin = false): bool
    {
        $review = $this->reviewRepository->find($reviewId);

        if (!$review) {
            throw new ReviewException('Review not found');
        }

        // Verify ownership or admin
        if (!$isAdmin && $review['user_id'] !== $userId) {
            throw new ReviewException('You can only delete your own reviews');
        }

        $result = $this->reviewRepository->delete($reviewId);

        $this->logger->info('Product review deleted', [
            'review_id' => $reviewId,
            'deleted_by_user_id' => $userId,
            'is_admin' => $isAdmin
        ]);

        return $result;
    }

    /**
     * Get reviews for a product
     */
    public function getProductReviews(int $productId, int $page = 1, int $perPage = 10): array
    {
        $offset = ($page - 1) * $perPage;
        return $this->reviewRepository->getByProduct($productId, $perPage, $offset);
    }

    /**
     * Get user's reviews
     */
    public function getUserReviews(int $userId, int $page = 1, int $perPage = 10): array
    {
        $offset = ($page - 1) * $perPage;
        return $this->reviewRepository->getByUser($userId, $perPage, $offset);
    }

    /**
     * Mark review as helpful
     */
    public function markHelpful(int $reviewId, int $userId): bool
    {
        if ($this->reviewRepository->hasMarkedHelpful($reviewId, $userId)) {
            // Unmark if already marked
            return $this->reviewRepository->unmarkHelpful($reviewId, $userId);
        }

        return $this->reviewRepository->markHelpful($reviewId, $userId);
    }

    /**
     * Check if user has reviewed a product
     */
    public function hasUserReviewed(int $productId, int $userId): bool
    {
        return $this->reviewRepository->hasUserReviewed($productId, $userId);
    }

    /**
     * Get pending reviews for moderation
     */
    public function getPendingReviews(int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        return $this->reviewRepository->getPendingReviews($perPage, $offset);
    }

    /**
     * Approve a review
     */
    public function approveReview(int $reviewId): bool
    {
        $result = $this->reviewRepository->approve($reviewId);

        $this->logger->info('Review approved', ['review_id' => $reviewId]);

        return $result;
    }

    /**
     * Reject a review
     */
    public function rejectReview(int $reviewId): bool
    {
        $result = $this->reviewRepository->reject($reviewId);

        $this->logger->warning('Review rejected', ['review_id' => $reviewId]);

        return $result;
    }

    /**
     * Get rating distribution for a product
     */
    public function getRatingDistribution(int $productId): array
    {
        // This would typically be a repository method, simplified here
        return [
            5 => 0,
            4 => 0,
            3 => 0,
            2 => 0,
            1 => 0
        ];
    }
}
