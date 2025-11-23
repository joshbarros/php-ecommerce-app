<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NotFoundException;
use App\Repositories\Interfaces\CategoryRepositoryInterface;
use Psr\Log\LoggerInterface;

final class CategoryService
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Get category by ID
     */
    public function getCategoryById(int $id): array
    {
        $category = $this->categoryRepository->findById($id);

        if (!$category) {
            throw new NotFoundException("Category not found");
        }

        return $category;
    }

    /**
     * Get category by slug
     */
    public function getCategoryBySlug(string $slug): array
    {
        $category = $this->categoryRepository->findBySlug($slug);

        if (!$category) {
            throw new NotFoundException("Category not found");
        }

        return $category;
    }

    /**
     * Get all active categories
     */
    public function getActiveCategories(): array
    {
        return $this->categoryRepository->findActive();
    }

    /**
     * Get root categories (for navigation)
     */
    public function getRootCategories(): array
    {
        return $this->categoryRepository->findRootCategories();
    }

    /**
     * Get child categories
     */
    public function getChildCategories(int $parentId): array
    {
        return $this->categoryRepository->findChildren($parentId);
    }

    /**
     * Build category tree for navigation
     */
    public function getCategoryTree(): array
    {
        $rootCategories = $this->getRootCategories();

        return array_map(function ($category) {
            $category['children'] = $this->getChildCategories($category['id']);
            return $category;
        }, $rootCategories);
    }
}
