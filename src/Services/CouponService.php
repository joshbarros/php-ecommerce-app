<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\Interfaces\CouponRepositoryInterface;
use App\Exceptions\CouponException;
use Psr\Log\LoggerInterface;

final class CouponService
{
    public function __construct(
        private readonly CouponRepositoryInterface $couponRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Validate and apply coupon to an order
     *
     * @throws CouponException
     */
    public function applyCoupon(
        string $code,
        float $subtotal,
        array $cartItems,
        ?int $userId = null
    ): array {
        $coupon = $this->couponRepository->findByCode($code);

        if (!$coupon) {
            throw new CouponException('Invalid coupon code');
        }

        // Validate coupon
        $this->validateCoupon($coupon, $subtotal, $cartItems, $userId);

        // Calculate discount
        $discountAmount = $this->calculateDiscount($coupon, $subtotal, $cartItems);

        $this->logger->info('Coupon applied', [
            'code' => $code,
            'discount_amount' => $discountAmount,
            'user_id' => $userId
        ]);

        return [
            'coupon_id' => $coupon['id'],
            'coupon_code' => $coupon['code'],
            'discount_amount' => $discountAmount,
            'type' => $coupon['type'],
            'description' => $coupon['description']
        ];
    }

    /**
     * Validate coupon eligibility
     *
     * @throws CouponException
     */
    private function validateCoupon(
        array $coupon,
        float $subtotal,
        array $cartItems,
        ?int $userId
    ): void {
        // Check if active
        if (!$coupon['is_active']) {
            throw new CouponException('This coupon is no longer active');
        }

        // Check start date
        if ($coupon['starts_at'] && strtotime($coupon['starts_at']) > time()) {
            throw new CouponException('This coupon is not yet valid');
        }

        // Check expiration
        if ($coupon['expires_at'] && strtotime($coupon['expires_at']) < time()) {
            throw new CouponException('This coupon has expired');
        }

        // Check usage limit
        if ($coupon['usage_limit'] && $coupon['usage_count'] >= $coupon['usage_limit']) {
            throw new CouponException('This coupon has reached its usage limit');
        }

        // Check minimum order amount
        if ($subtotal < $coupon['minimum_order_amount']) {
            throw new CouponException(
                sprintf(
                    'Minimum order amount of $%.2f required for this coupon',
                    $coupon['minimum_order_amount']
                )
            );
        }

        // Check per-user usage limit
        if ($userId) {
            $userUsageCount = $this->couponRepository->getUserUsageCount(
                $coupon['id'],
                $userId
            );

            if ($userUsageCount >= $coupon['usage_limit_per_user']) {
                throw new CouponException('You have already used this coupon the maximum number of times');
            }

            // Check first order only
            if ($coupon['first_order_only']) {
                $hasOrdered = $this->couponRepository->hasUserPlacedOrder($userId);
                if ($hasOrdered) {
                    throw new CouponException('This coupon is only valid for first-time customers');
                }
            }
        }

        // Check customer restrictions
        if ($coupon['customer_ids']) {
            $allowedCustomers = json_decode($coupon['customer_ids'], true);
            if ($userId && !in_array($userId, $allowedCustomers)) {
                throw new CouponException('This coupon is not available for your account');
            }
        }

        // Check product restrictions
        if ($coupon['applicable_product_ids']) {
            $applicableProducts = json_decode($coupon['applicable_product_ids'], true);
            $hasApplicableProduct = false;

            foreach ($cartItems as $item) {
                if (in_array($item['product_id'], $applicableProducts)) {
                    $hasApplicableProduct = true;
                    break;
                }
            }

            if (!$hasApplicableProduct) {
                throw new CouponException('This coupon is not valid for items in your cart');
            }
        }

        // Check excluded products
        if ($coupon['excluded_product_ids']) {
            $excludedProducts = json_decode($coupon['excluded_product_ids'], true);

            foreach ($cartItems as $item) {
                if (in_array($item['product_id'], $excludedProducts)) {
                    throw new CouponException('Your cart contains items excluded from this coupon');
                }
            }
        }
    }

    /**
     * Calculate discount amount
     */
    private function calculateDiscount(array $coupon, float $subtotal, array $cartItems): float
    {
        switch ($coupon['type']) {
            case 'percentage':
                $discount = $subtotal * ($coupon['value'] / 100);
                return round($discount, 2);

            case 'fixed_amount':
                // Don't let discount exceed subtotal
                return min($coupon['value'], $subtotal);

            case 'free_shipping':
                // Shipping discount is handled separately
                return 0.00;

            default:
                return 0.00;
        }
    }

    /**
     * Check if coupon provides free shipping
     */
    public function isFreeShipping(string $code): bool
    {
        $coupon = $this->couponRepository->findByCode($code);

        return $coupon && $coupon['type'] === 'free_shipping' && $coupon['is_active'];
    }

    /**
     * Record coupon usage after order is placed
     */
    public function recordUsage(int $couponId, int $orderId, ?int $userId, float $discountAmount): bool
    {
        return $this->couponRepository->recordUsage($couponId, $orderId, $userId, $discountAmount);
    }

    /**
     * Create a new coupon
     */
    public function createCoupon(array $data): array
    {
        // Validate code format
        if (!preg_match('/^[A-Z0-9-_]+$/', $data['code'])) {
            throw new CouponException('Coupon code must contain only uppercase letters, numbers, hyphens, and underscores');
        }

        // Validate value based on type
        if ($data['type'] === 'percentage' && ($data['value'] < 0 || $data['value'] > 100)) {
            throw new CouponException('Percentage discount must be between 0 and 100');
        }

        if (in_array($data['type'], ['fixed_amount', 'percentage']) && $data['value'] < 0) {
            throw new CouponException('Discount value cannot be negative');
        }

        $coupon = $this->couponRepository->create($data);

        $this->logger->info('Coupon created', [
            'code' => $data['code'],
            'type' => $data['type']
        ]);

        return $coupon;
    }

    /**
     * Update coupon
     */
    public function updateCoupon(int $id, array $data): bool
    {
        return $this->couponRepository->update($id, $data);
    }

    /**
     * Delete coupon
     */
    public function deleteCoupon(int $id): bool
    {
        return $this->couponRepository->delete($id);
    }

    /**
     * Get all coupons with pagination
     */
    public function getAllCoupons(int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        return $this->couponRepository->getAll($perPage, $offset);
    }

    /**
     * Get active coupons
     */
    public function getActiveCoupons(int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        return $this->couponRepository->getActive($perPage, $offset);
    }

    /**
     * Get coupon usage statistics
     */
    public function getCouponStats(int $couponId): array
    {
        return $this->couponRepository->getUsageStats($couponId);
    }

    /**
     * Get coupon by code (for admin)
     */
    public function getCouponByCode(string $code): ?array
    {
        return $this->couponRepository->findByCode($code);
    }
}
