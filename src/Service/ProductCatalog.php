<?php

namespace App\Service;

use App\Entity\Product;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;

/**
 * Storefront view of the product table. Controllers and templates depend on this
 * array shape, not on the entities.
 */
final class ProductCatalog
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly CategoryRepository $categories,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAll(?string $categorySlug = null): array
    {
        if (null !== $categorySlug && !isset($this->getCategories()[$categorySlug])) {
            $categorySlug = null;
        }

        return array_map($this->hydrate(...), $this->products->findCatalog($categorySlug));
    }

    /**
     * @return array<string, string> slug => label
     */
    public function getCategories(): array
    {
        $categories = [];
        foreach ($this->categories->findBy([], ['id' => 'ASC']) as $category) {
            $categories[$category->getSlug()] = $category->getCategoryname();
        }

        return $categories;
    }

    /**
     * @return array<string, mixed>
     */
    public function hydrate(Product $product): array
    {
        $stock = $product->getStock();

        if ($stock <= 0) {
            $level = 'out';
            $status = 'Out of Stock';
        } elseif ($stock <= Product::LOW_STOCK_THRESHOLD) {
            $level = 'low';
            $status = sprintf('Only %d left', $stock);
        } else {
            $level = 'ok';
            $status = sprintf('%d in stock', $stock);
        }

        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'category' => $product->getCategory()->getCategoryname(),
            'description' => $product->getDescription(),
            'price' => $product->getPrice(),
            'stock' => $stock,
            'badge' => $product->getBadge(),
            'image' => $product->getImage(),
            'stockStatus' => $status,
            'stockLevel' => $level,
            'isAvailable' => $stock > 0,
        ];
    }
}
