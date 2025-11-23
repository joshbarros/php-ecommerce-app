<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\Interfaces\CartRepositoryInterface;
use PDO;

final class CartRepository implements CartRepositoryInterface
{
    public function __construct(
        private readonly PDO $db
    ) {
    }

    public function findByUserId(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM cart WHERE user_id = :user_id'
        );

        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    public function findBySessionId(string $sessionId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM cart WHERE session_id = :session_id'
        );

        $stmt->execute(['session_id' => $sessionId]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO cart (user_id, session_id)
             VALUES (:user_id, :session_id)
             RETURNING id'
        );

        $stmt->execute([
            'user_id' => $data['user_id'] ?? null,
            'session_id' => $data['session_id'] ?? null
        ]);

        $result = $stmt->fetch();
        return (int) $result['id'];
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        foreach ($data as $key => $value) {
            if ($key !== 'id') {
                $fields[] = "{$key} = :{$key}";
                $params[$key] = $value;
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = 'UPDATE cart SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);

        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $this->db->beginTransaction();

        try {
            // Delete cart items first
            $stmt = $this->db->prepare('DELETE FROM cart_items WHERE cart_id = :cart_id');
            $stmt->execute(['cart_id' => $id]);

            // Delete cart
            $stmt = $this->db->prepare('DELETE FROM cart WHERE id = :id');
            $stmt->execute(['id' => $id]);

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getItems(int $cartId): array
    {
        $stmt = $this->db->prepare(
            'SELECT ci.*,
                    p.name as product_name,
                    p.slug as product_slug,
                    p.sku,
                    p.stock_quantity,
                    p.price as current_price,
                    p.is_active as product_is_active,
                    (SELECT image_url FROM product_images
                     WHERE product_id = p.id AND is_primary = true
                     LIMIT 1) as product_image
             FROM cart_items ci
             INNER JOIN products p ON ci.product_id = p.id
             WHERE ci.cart_id = :cart_id
             ORDER BY ci.created_at ASC'
        );

        $stmt->execute(['cart_id' => $cartId]);
        return $stmt->fetchAll();
    }

    public function addItem(int $cartId, int $productId, int $quantity, float $price): int
    {
        // Check if item already exists
        $stmt = $this->db->prepare(
            'SELECT id, quantity FROM cart_items
             WHERE cart_id = :cart_id AND product_id = :product_id'
        );

        $stmt->execute([
            'cart_id' => $cartId,
            'product_id' => $productId
        ]);

        $existingItem = $stmt->fetch();

        if ($existingItem) {
            // Update quantity
            $newQuantity = $existingItem['quantity'] + $quantity;
            $stmt = $this->db->prepare(
                'UPDATE cart_items
                 SET quantity = :quantity, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id
                 RETURNING id'
            );

            $stmt->execute([
                'quantity' => $newQuantity,
                'id' => $existingItem['id']
            ]);

            return (int) $existingItem['id'];
        }

        // Insert new item
        $stmt = $this->db->prepare(
            'INSERT INTO cart_items (cart_id, product_id, quantity, price)
             VALUES (:cart_id, :product_id, :quantity, :price)
             RETURNING id'
        );

        $stmt->execute([
            'cart_id' => $cartId,
            'product_id' => $productId,
            'quantity' => $quantity,
            'price' => $price
        ]);

        $result = $stmt->fetch();
        return (int) $result['id'];
    }

    public function updateItemQuantity(int $cartId, int $productId, int $quantity): bool
    {
        if ($quantity <= 0) {
            return $this->removeItem($cartId, $productId);
        }

        $stmt = $this->db->prepare(
            'UPDATE cart_items
             SET quantity = :quantity, updated_at = CURRENT_TIMESTAMP
             WHERE cart_id = :cart_id AND product_id = :product_id'
        );

        return $stmt->execute([
            'quantity' => $quantity,
            'cart_id' => $cartId,
            'product_id' => $productId
        ]);
    }

    public function removeItem(int $cartId, int $productId): bool
    {
        $stmt = $this->db->prepare(
            'DELETE FROM cart_items
             WHERE cart_id = :cart_id AND product_id = :product_id'
        );

        return $stmt->execute([
            'cart_id' => $cartId,
            'product_id' => $productId
        ]);
    }

    public function clearItems(int $cartId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM cart_items WHERE cart_id = :cart_id');
        return $stmt->execute(['cart_id' => $cartId]);
    }

    public function getCartTotal(int $cartId): float
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(quantity * price), 0) as total
             FROM cart_items
             WHERE cart_id = :cart_id'
        );

        $stmt->execute(['cart_id' => $cartId]);
        $result = $stmt->fetch();

        return (float) $result['total'];
    }

    public function getCartItemCount(int $cartId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(quantity), 0) as count
             FROM cart_items
             WHERE cart_id = :cart_id'
        );

        $stmt->execute(['cart_id' => $cartId]);
        $result = $stmt->fetch();

        return (int) $result['count'];
    }

    public function mergeCart(int $guestCartId, int $userCartId): bool
    {
        $this->db->beginTransaction();

        try {
            // Get guest cart items
            $guestItems = $this->getItems($guestCartId);

            foreach ($guestItems as $item) {
                // Check if item exists in user cart
                $stmt = $this->db->prepare(
                    'SELECT id, quantity FROM cart_items
                     WHERE cart_id = :cart_id AND product_id = :product_id'
                );

                $stmt->execute([
                    'cart_id' => $userCartId,
                    'product_id' => $item['product_id']
                ]);

                $userItem = $stmt->fetch();

                if ($userItem) {
                    // Update quantity (add guest quantity to user quantity)
                    $newQuantity = $userItem['quantity'] + $item['quantity'];
                    $stmt = $this->db->prepare(
                        'UPDATE cart_items
                         SET quantity = :quantity, updated_at = CURRENT_TIMESTAMP
                         WHERE id = :id'
                    );

                    $stmt->execute([
                        'quantity' => $newQuantity,
                        'id' => $userItem['id']
                    ]);
                } else {
                    // Insert item into user cart
                    $stmt = $this->db->prepare(
                        'INSERT INTO cart_items (cart_id, product_id, quantity, price)
                         VALUES (:cart_id, :product_id, :quantity, :price)'
                    );

                    $stmt->execute([
                        'cart_id' => $userCartId,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price']
                    ]);
                }
            }

            // Delete guest cart
            $this->delete($guestCartId);

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function deleteOldCarts(int $daysOld = 30): int
    {
        $this->db->beginTransaction();

        try {
            // Get old cart IDs
            $stmt = $this->db->prepare(
                'SELECT id FROM cart
                 WHERE updated_at < NOW() - INTERVAL \':days days\'
                 AND user_id IS NULL'
            );

            $stmt->execute(['days' => $daysOld]);
            $oldCarts = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($oldCarts)) {
                $this->db->commit();
                return 0;
            }

            // Delete cart items
            $placeholders = implode(',', array_fill(0, count($oldCarts), '?'));
            $stmt = $this->db->prepare(
                "DELETE FROM cart_items WHERE cart_id IN ($placeholders)"
            );
            $stmt->execute($oldCarts);

            // Delete carts
            $stmt = $this->db->prepare(
                "DELETE FROM cart WHERE id IN ($placeholders)"
            );
            $stmt->execute($oldCarts);

            $deletedCount = count($oldCarts);

            $this->db->commit();
            return $deletedCount;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
