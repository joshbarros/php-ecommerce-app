<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\Interfaces\ProductRepositoryInterface;
use PDO;

final class ProductRepository implements ProductRepositoryInterface
{
    public function __construct(
        private readonly PDO $db
    ) {
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, c.name as category_name, c.slug as category_slug
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE p.id = :id AND p.is_active = true'
        );

        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();

        if ($result) {
            $result = $this->loadProductImages($result);
        }

        return $result ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, c.name as category_name, c.slug as category_slug
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE p.slug = :slug AND p.is_active = true'
        );

        $stmt->execute(['slug' => $slug]);
        $result = $stmt->fetch();

        if ($result) {
            $result = $this->loadProductImages($result);
        }

        return $result ?: null;
    }

    public function findAll(int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, c.name as category_name, c.slug as category_slug
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE p.is_active = true
             ORDER BY p.created_at DESC
             LIMIT :limit OFFSET :offset'
        );

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $products = $stmt->fetchAll();

        return array_map([$this, 'loadProductImages'], $products);
    }

    public function findByCategory(int $categoryId, int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, c.name as category_name, c.slug as category_slug
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE p.category_id = :category_id AND p.is_active = true
             ORDER BY p.created_at DESC
             LIMIT :limit OFFSET :offset'
        );

        $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $products = $stmt->fetchAll();

        return array_map([$this, 'loadProductImages'], $products);
    }

    public function findFeatured(int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, c.name as category_name, c.slug as category_slug
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE p.is_featured = true AND p.is_active = true
             ORDER BY p.created_at DESC
             LIMIT :limit'
        );

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $products = $stmt->fetchAll();

        return array_map([$this, 'loadProductImages'], $products);
    }

    public function search(string $query, int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, c.name as category_name, c.slug as category_slug,
                    ts_rank(p.search_vector, plainto_tsquery(:query)) as rank
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE p.search_vector @@ plainto_tsquery(:query)
             AND p.is_active = true
             ORDER BY rank DESC, p.created_at DESC
             LIMIT :limit OFFSET :offset'
        );

        $stmt->bindValue(':query', $query, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $products = $stmt->fetchAll();

        return array_map([$this, 'loadProductImages'], $products);
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO products (
                sku, name, slug, description, short_description,
                price, compare_price, cost, stock_quantity,
                category_id, is_active, is_featured
            ) VALUES (
                :sku, :name, :slug, :description, :short_description,
                :price, :compare_price, :cost, :stock_quantity,
                :category_id, :is_active, :is_featured
            ) RETURNING id'
        );

        $stmt->execute([
            'sku' => $data['sku'],
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'short_description' => $data['short_description'] ?? null,
            'price' => $data['price'],
            'compare_price' => $data['compare_price'] ?? null,
            'cost' => $data['cost'] ?? null,
            'stock_quantity' => $data['stock_quantity'] ?? 0,
            'category_id' => $data['category_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'is_featured' => $data['is_featured'] ?? false
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

        $sql = 'UPDATE products SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);

        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        // Soft delete
        $stmt = $this->db->prepare('UPDATE products SET is_active = false WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function decreaseStock(int $id, int $quantity): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE products
             SET stock_quantity = stock_quantity - :quantity
             WHERE id = :id AND stock_quantity >= :quantity'
        );

        return $stmt->execute([
            'id' => $id,
            'quantity' => $quantity
        ]);
    }

    public function increaseStock(int $id, int $quantity): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE products
             SET stock_quantity = stock_quantity + :quantity
             WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $id,
            'quantity' => $quantity
        ]);
    }

    public function countAll(): int
    {
        $stmt = $this->db->query('SELECT COUNT(*) as count FROM products WHERE is_active = true');
        $result = $stmt->fetch();
        return (int) $result['count'];
    }

    public function countByCategory(int $categoryId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) as count FROM products
             WHERE category_id = :category_id AND is_active = true'
        );
        $stmt->execute(['category_id' => $categoryId]);
        $result = $stmt->fetch();
        return (int) $result['count'];
    }

    public function countSearch(string $query): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) as count FROM products
             WHERE search_vector @@ plainto_tsquery(:query) AND is_active = true'
        );
        $stmt->execute(['query' => $query]);
        $result = $stmt->fetch();
        return (int) $result['count'];
    }

    /**
     * Load product images for a product
     */
    private function loadProductImages(array $product): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, image_url, alt_text, is_primary, sort_order
             FROM product_images
             WHERE product_id = :product_id
             ORDER BY is_primary DESC, sort_order ASC'
        );

        $stmt->execute(['product_id' => $product['id']]);
        $product['images'] = $stmt->fetchAll();

        // Set primary image
        $primaryImage = array_filter($product['images'], fn($img) => $img['is_primary']);
        $product['primary_image'] = !empty($primaryImage)
            ? reset($primaryImage)['image_url']
            : null;

        return $product;
    }

    /**
     * Add image to product
     */
    public function addImage(int $productId, string $imageUrl, ?string $altText = null, bool $isPrimary = false): int
    {
        // If this is primary, unset other primary images
        if ($isPrimary) {
            $this->db->prepare(
                'UPDATE product_images SET is_primary = false WHERE product_id = :product_id'
            )->execute(['product_id' => $productId]);
        }

        $stmt = $this->db->prepare(
            'INSERT INTO product_images (product_id, image_url, alt_text, is_primary)
             VALUES (:product_id, :image_url, :alt_text, :is_primary)
             RETURNING id'
        );

        $stmt->execute([
            'product_id' => $productId,
            'image_url' => $imageUrl,
            'alt_text' => $altText,
            'is_primary' => $isPrimary
        ]);

        $result = $stmt->fetch();
        return (int) $result['id'];
    }
}
