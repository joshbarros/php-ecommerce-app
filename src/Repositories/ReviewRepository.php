<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\Interfaces\ReviewRepositoryInterface;
use PDO;

final class ReviewRepository implements ReviewRepositoryInterface
{
    public function __construct(
        private readonly PDO $db
    ) {
    }

    public function create(array $data): array
    {
        $stmt = $this->db->prepare(
            'INSERT INTO product_reviews (
                product_id, user_id, rating, title, comment, is_verified_purchase
            ) VALUES (
                :product_id, :user_id, :rating, :title, :comment, :is_verified_purchase
            ) RETURNING id, uuid, product_id, user_id, rating, title, comment,
                        is_verified_purchase, is_approved, helpful_count, created_at'
        );

        $stmt->execute([
            'product_id' => $data['product_id'],
            'user_id' => $data['user_id'],
            'rating' => $data['rating'],
            'title' => $data['title'] ?? null,
            'comment' => $data['comment'] ?? null,
            'is_verified_purchase' => $data['is_verified_purchase'] ?? false
        ]);

        return $stmt->fetch();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT r.*, u.name as user_name, u.email as user_email
             FROM product_reviews r
             JOIN users u ON r.user_id = u.id
             WHERE r.id = :id'
        );

        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    public function findByUuid(string $uuid): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT r.*, u.name as user_name, u.email as user_email
             FROM product_reviews r
             JOIN users u ON r.user_id = u.id
             WHERE r.uuid = :uuid'
        );

        $stmt->execute(['uuid' => $uuid]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    public function getByProduct(int $productId, int $limit = 10, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            'SELECT r.*, u.name as user_name
             FROM product_reviews r
             JOIN users u ON r.user_id = u.id
             WHERE r.product_id = :product_id
               AND r.is_approved = true
             ORDER BY r.helpful_count DESC, r.created_at DESC
             LIMIT :limit OFFSET :offset'
        );

        $stmt->bindValue('product_id', $productId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getByUser(int $userId, int $limit = 10, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            'SELECT r.*, p.name as product_name, p.slug as product_slug
             FROM product_reviews r
             JOIN products p ON r.product_id = p.id
             WHERE r.user_id = :user_id
             ORDER BY r.created_at DESC
             LIMIT :limit OFFSET :offset'
        );

        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function hasUserReviewed(int $productId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM product_reviews
             WHERE product_id = :product_id AND user_id = :user_id'
        );

        $stmt->execute([
            'product_id' => $productId,
            'user_id' => $userId
        ]);

        return $stmt->fetchColumn() > 0;
    }

    public function isVerifiedPurchase(int $productId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM order_items oi
             JOIN orders o ON oi.order_id = o.id
             WHERE oi.product_id = :product_id
               AND o.user_id = :user_id
               AND o.status IN (\'processing\', \'shipped\', \'delivered\')'
        );

        $stmt->execute([
            'product_id' => $productId,
            'user_id' => $userId
        ]);

        return $stmt->fetchColumn() > 0;
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        if (isset($data['rating'])) {
            $fields[] = 'rating = :rating';
            $params['rating'] = $data['rating'];
        }

        if (isset($data['title'])) {
            $fields[] = 'title = :title';
            $params['title'] = $data['title'];
        }

        if (isset($data['comment'])) {
            $fields[] = 'comment = :comment';
            $params['comment'] = $data['comment'];
        }

        if (empty($fields)) {
            return true;
        }

        $sql = 'UPDATE product_reviews SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);

        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM product_reviews WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function markHelpful(int $reviewId, int $userId): bool
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO review_helpful_votes (review_id, user_id)
                 VALUES (:review_id, :user_id)
                 ON CONFLICT (review_id, user_id) DO NOTHING'
            );

            return $stmt->execute([
                'review_id' => $reviewId,
                'user_id' => $userId
            ]);
        } catch (\PDOException $e) {
            return false;
        }
    }

    public function unmarkHelpful(int $reviewId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'DELETE FROM review_helpful_votes
             WHERE review_id = :review_id AND user_id = :user_id'
        );

        return $stmt->execute([
            'review_id' => $reviewId,
            'user_id' => $userId
        ]);
    }

    public function hasMarkedHelpful(int $reviewId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM review_helpful_votes
             WHERE review_id = :review_id AND user_id = :user_id'
        );

        $stmt->execute([
            'review_id' => $reviewId,
            'user_id' => $userId
        ]);

        return $stmt->fetchColumn() > 0;
    }

    public function getPendingReviews(int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            'SELECT r.*, u.name as user_name, p.name as product_name
             FROM product_reviews r
             JOIN users u ON r.user_id = u.id
             JOIN products p ON r.product_id = p.id
             WHERE r.is_approved = false
             ORDER BY r.created_at ASC
             LIMIT :limit OFFSET :offset'
        );

        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function approve(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE product_reviews SET is_approved = true WHERE id = :id'
        );

        return $stmt->execute(['id' => $id]);
    }

    public function reject(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE product_reviews SET is_approved = false WHERE id = :id'
        );

        return $stmt->execute(['id' => $id]);
    }
}
