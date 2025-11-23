<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

interface ProductRepositoryInterface
{
    public function findById(int $id): ?array;
    public function findBySlug(string $slug): ?array;
    public function findAll(int $limit = 20, int $offset = 0): array;
    public function findByCategory(int $categoryId, int $limit = 20, int $offset = 0): array;
    public function findFeatured(int $limit = 10): array;
    public function search(string $query, int $limit = 20, int $offset = 0): array;
    public function create(array $data): int;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
    public function decreaseStock(int $id, int $quantity): bool;
    public function increaseStock(int $id, int $quantity): bool;
    public function countAll(): int;
    public function countByCategory(int $categoryId): int;
    public function countSearch(string $query): int;
}
