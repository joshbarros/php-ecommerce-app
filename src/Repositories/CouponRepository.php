<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\Interfaces\CouponRepositoryInterface;
use PDO;

final class CouponRepository implements CouponRepositoryInterface
{
    public function __construct(
        private readonly PDO $db
    ) {
    }

    public function findByCode(string $code): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM coupons WHERE UPPER(code) = UPPER(:code)'
        );

        $stmt->execute(['code' => $code]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM coupons WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    public function create(array $data): array
    {
        $stmt = $this->db->prepare(
            'INSERT INTO coupons (
                code, type, value, description,
                usage_limit, usage_limit_per_user, minimum_order_amount,
                applicable_product_ids, applicable_category_ids,
                excluded_product_ids, excluded_category_ids,
                first_order_only, customer_ids,
                starts_at, expires_at, is_active, created_by
            ) VALUES (
                :code, :type, :value, :description,
                :usage_limit, :usage_limit_per_user, :minimum_order_amount,
                :applicable_product_ids, :applicable_category_ids,
                :excluded_product_ids, :excluded_category_ids,
                :first_order_only, :customer_ids,
                :starts_at, :expires_at, :is_active, :created_by
            ) RETURNING *'
        );

        $stmt->execute([
            'code' => strtoupper($data['code']),
            'type' => $data['type'],
            'value' => $data['value'],
            'description' => $data['description'] ?? null,
            'usage_limit' => $data['usage_limit'] ?? null,
            'usage_limit_per_user' => $data['usage_limit_per_user'] ?? 1,
            'minimum_order_amount' => $data['minimum_order_amount'] ?? 0.00,
            'applicable_product_ids' => $data['applicable_product_ids'] ?? null,
            'applicable_category_ids' => $data['applicable_category_ids'] ?? null,
            'excluded_product_ids' => $data['excluded_product_ids'] ?? null,
            'excluded_category_ids' => $data['excluded_category_ids'] ?? null,
            'first_order_only' => $data['first_order_only'] ?? false,
            'customer_ids' => $data['customer_ids'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $data['created_by'] ?? null
        ]);

        return $stmt->fetch();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        $allowedFields = [
            'code', 'type', 'value', 'description',
            'usage_limit', 'usage_limit_per_user', 'minimum_order_amount',
            'starts_at', 'expires_at', 'is_active'
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }

        if (empty($fields)) {
            return true;
        }

        $sql = 'UPDATE coupons SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);

        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM coupons WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function getAll(int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*, u.name as created_by_name
             FROM coupons c
             LEFT JOIN users u ON c.created_by = u.id
             ORDER BY c.created_at DESC
             LIMIT :limit OFFSET :offset'
        );

        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getActive(int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM coupons
             WHERE is_active = true
               AND (starts_at IS NULL OR starts_at <= CURRENT_TIMESTAMP)
               AND (expires_at IS NULL OR expires_at >= CURRENT_TIMESTAMP)
               AND (usage_limit IS NULL OR usage_count < usage_limit)
             ORDER BY created_at DESC
             LIMIT :limit OFFSET :offset'
        );

        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function recordUsage(int $couponId, int $orderId, ?int $userId, float $discountAmount): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO coupon_usages (coupon_id, order_id, user_id, discount_amount)
             VALUES (:coupon_id, :order_id, :user_id, :discount_amount)'
        );

        return $stmt->execute([
            'coupon_id' => $couponId,
            'order_id' => $orderId,
            'user_id' => $userId,
            'discount_amount' => $discountAmount
        ]);
    }

    public function getUserUsageCount(int $couponId, int $userId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM coupon_usages
             WHERE coupon_id = :coupon_id AND user_id = :user_id'
        );

        $stmt->execute([
            'coupon_id' => $couponId,
            'user_id' => $userId
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function hasUserPlacedOrder(int $userId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM orders WHERE user_id = :user_id'
        );

        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchColumn() > 0;
    }

    public function getUsageStats(int $couponId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                COUNT(*) as total_uses,
                COUNT(DISTINCT user_id) as unique_users,
                SUM(discount_amount) as total_discount,
                AVG(discount_amount) as avg_discount,
                MIN(created_at) as first_used,
                MAX(created_at) as last_used
             FROM coupon_usages
             WHERE coupon_id = :coupon_id'
        );

        $stmt->execute(['coupon_id' => $couponId]);

        return $stmt->fetch() ?: [];
    }
}
