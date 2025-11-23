<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\Interfaces\CategoryRepositoryInterface;
use PDO;

final class CategoryRepository implements CategoryRepositoryInterface
{
    public function __construct(
        private readonly PDO $db
    ) {
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*,
                    COUNT(p.id) as product_count,
                    pc.name as parent_name
             FROM categories c
             LEFT JOIN products p ON p.category_id = c.id AND p.is_active = true
             LEFT JOIN categories pc ON c.parent_id = pc.id
             WHERE c.id = :id
             GROUP BY c.id, pc.name'
        );

        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*,
                    COUNT(p.id) as product_count,
                    pc.name as parent_name
             FROM categories c
             LEFT JOIN products p ON p.category_id = c.id AND p.is_active = true
             LEFT JOIN categories pc ON c.parent_id = pc.id
             WHERE c.slug = :slug
             GROUP BY c.id, pc.name'
        );

        $stmt->execute(['slug' => $slug]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    public function findAll(): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*,
                    COUNT(p.id) as product_count
             FROM categories c
             LEFT JOIN products p ON p.category_id = c.id AND p.is_active = true
             GROUP BY c.id
             ORDER BY c.sort_order ASC, c.name ASC'
        );

        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function findActive(): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*,
                    COUNT(p.id) as product_count
             FROM categories c
             LEFT JOIN products p ON p.category_id = c.id AND p.is_active = true
             WHERE c.is_active = true
             GROUP BY c.id
             ORDER BY c.sort_order ASC, c.name ASC'
        );

        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function findRootCategories(): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*,
                    COUNT(p.id) as product_count
             FROM categories c
             LEFT JOIN products p ON p.category_id = c.id AND p.is_active = true
             WHERE c.parent_id IS NULL AND c.is_active = true
             GROUP BY c.id
             ORDER BY c.sort_order ASC, c.name ASC'
        );

        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function findChildren(int $parentId): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*,
                    COUNT(p.id) as product_count
             FROM categories c
             LEFT JOIN products p ON p.category_id = c.id AND p.is_active = true
             WHERE c.parent_id = :parent_id AND c.is_active = true
             GROUP BY c.id
             ORDER BY c.sort_order ASC, c.name ASC'
        );

        $stmt->execute(['parent_id' => $parentId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO categories (
                name, slug, description, parent_id,
                image_url, is_active, sort_order
            ) VALUES (
                :name, :slug, :description, :parent_id,
                :image_url, :is_active, :sort_order
            ) RETURNING id'
        );

        $stmt->execute([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
            'image_url' => $data['image_url'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0
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

        $sql = 'UPDATE categories SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);

        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        // Soft delete
        $stmt = $this->db->prepare('UPDATE categories SET is_active = false WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }
}
