<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\Interfaces\OrderRepositoryInterface;
use PDO;

final class OrderRepository implements OrderRepositoryInterface
{
    public function __construct(
        private readonly PDO $db
    ) {
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT o.*,
                    u.email as user_email,
                    u.first_name,
                    u.last_name
             FROM orders o
             LEFT JOIN users u ON o.user_id = u.id
             WHERE o.id = :id'
        );

        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    public function findByUuid(string $uuid): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT o.*,
                    u.email as user_email,
                    u.first_name,
                    u.last_name
             FROM orders o
             LEFT JOIN users u ON o.user_id = u.id
             WHERE o.uuid = :uuid'
        );

        $stmt->execute(['uuid' => $uuid]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    public function findByUserId(int $userId, int $limit = 10, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            'SELECT o.*
             FROM orders o
             WHERE o.user_id = :user_id
             ORDER BY o.created_at DESC
             LIMIT :limit OFFSET :offset'
        );

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getOrderItems(int $orderId): array
    {
        $stmt = $this->db->prepare(
            'SELECT oi.*,
                    p.name as product_name,
                    p.slug as product_slug,
                    p.sku as product_sku
             FROM order_items oi
             LEFT JOIN products p ON oi.product_id = p.id
             WHERE oi.order_id = :order_id
             ORDER BY oi.id ASC'
        );

        $stmt->execute(['order_id' => $orderId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO orders (
                user_id, order_number, status,
                subtotal, tax, shipping, total,
                currency, payment_method, payment_status,
                shipping_first_name, shipping_last_name, shipping_email,
                shipping_phone, shipping_address_line1, shipping_address_line2,
                shipping_city, shipping_state, shipping_zip, shipping_country,
                billing_first_name, billing_last_name, billing_email,
                billing_phone, billing_address_line1, billing_address_line2,
                billing_city, billing_state, billing_zip, billing_country,
                notes
            ) VALUES (
                :user_id, :order_number, :status,
                :subtotal, :tax, :shipping, :total,
                :currency, :payment_method, :payment_status,
                :shipping_first_name, :shipping_last_name, :shipping_email,
                :shipping_phone, :shipping_address_line1, :shipping_address_line2,
                :shipping_city, :shipping_state, :shipping_zip, :shipping_country,
                :billing_first_name, :billing_last_name, :billing_email,
                :billing_phone, :billing_address_line1, :billing_address_line2,
                :billing_city, :billing_state, :billing_zip, :billing_country,
                :notes
            ) RETURNING id'
        );

        $stmt->execute([
            'user_id' => $data['user_id'] ?? null,
            'order_number' => $data['order_number'],
            'status' => $data['status'] ?? 'pending',
            'subtotal' => $data['subtotal'],
            'tax' => $data['tax'] ?? 0,
            'shipping' => $data['shipping'] ?? 0,
            'total' => $data['total'],
            'currency' => $data['currency'] ?? 'USD',
            'payment_method' => $data['payment_method'] ?? null,
            'payment_status' => $data['payment_status'] ?? 'pending',
            'shipping_first_name' => $data['shipping_first_name'],
            'shipping_last_name' => $data['shipping_last_name'],
            'shipping_email' => $data['shipping_email'],
            'shipping_phone' => $data['shipping_phone'] ?? null,
            'shipping_address_line1' => $data['shipping_address_line1'],
            'shipping_address_line2' => $data['shipping_address_line2'] ?? null,
            'shipping_city' => $data['shipping_city'],
            'shipping_state' => $data['shipping_state'],
            'shipping_zip' => $data['shipping_zip'],
            'shipping_country' => $data['shipping_country'] ?? 'US',
            'billing_first_name' => $data['billing_first_name'],
            'billing_last_name' => $data['billing_last_name'],
            'billing_email' => $data['billing_email'],
            'billing_phone' => $data['billing_phone'] ?? null,
            'billing_address_line1' => $data['billing_address_line1'],
            'billing_address_line2' => $data['billing_address_line2'] ?? null,
            'billing_city' => $data['billing_city'],
            'billing_state' => $data['billing_state'],
            'billing_zip' => $data['billing_zip'],
            'billing_country' => $data['billing_country'] ?? 'US',
            'notes' => $data['notes'] ?? null
        ]);

        $result = $stmt->fetch();
        return (int) $result['id'];
    }

    public function addItem(int $orderId, array $itemData): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO order_items (
                order_id, product_id, quantity, price, total
            ) VALUES (
                :order_id, :product_id, :quantity, :price, :total
            ) RETURNING id'
        );

        $stmt->execute([
            'order_id' => $orderId,
            'product_id' => $itemData['product_id'],
            'quantity' => $itemData['quantity'],
            'price' => $itemData['price'],
            'total' => $itemData['total']
        ]);

        $result = $stmt->fetch();
        return (int) $result['id'];
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE orders SET status = :status WHERE id = :id'
        );

        return $stmt->execute([
            'status' => $status,
            'id' => $id
        ]);
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

        $sql = 'UPDATE orders SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);

        return $stmt->execute($params);
    }

    public function countByUserId(int $userId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) as count FROM orders WHERE user_id = :user_id'
        );

        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch();

        return (int) $result['count'];
    }

    public function getRecentOrders(int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            'SELECT o.*,
                    u.email as user_email,
                    u.first_name,
                    u.last_name
             FROM orders o
             LEFT JOIN users u ON o.user_id = u.id
             ORDER BY o.created_at DESC
             LIMIT :limit'
        );

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function findByStatus(string $status, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            'SELECT o.*,
                    u.email as user_email,
                    u.first_name,
                    u.last_name
             FROM orders o
             LEFT JOIN users u ON o.user_id = u.id
             WHERE o.status = :status
             ORDER BY o.created_at DESC
             LIMIT :limit OFFSET :offset'
        );

        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
