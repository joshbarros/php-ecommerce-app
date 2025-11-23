<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

interface CategoryRepositoryInterface
{
    public function findById(int $id): ?array;
    public function findBySlug(string $slug): ?array;
    public function findAll(): array;
    public function findActive(): array;
    public function findRootCategories(): array;
    public function findChildren(int $parentId): array;
    public function create(array $data): int;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
}
