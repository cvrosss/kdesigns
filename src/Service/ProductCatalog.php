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
    /** The "All" view is two rows of products in display order, then one row of these. */
    private const ALL_LEADING_COUNT = 8;
    private const ALL_FEATURED_ROW = ['Citrus Sunrise Basket', 'Serenade Bloom Box', 'Floral Stand', 'Everlasting Glass Dome'];

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

        $products = $this->products->findCatalog($categorySlug);
        if (null === $categorySlug) {
            $products = $this->arrangeAll($products);
        }

        return array_map($this->hydrate(...), $products);
    }

    /**
     * The "All" view is a curated three-row selection; every product is still listed under its own
     * category filter. A product not in the leading rows or the featured row only shows under its category.
     *
     * @param list<Product> $products in display order
     *
     * @return list<Product>
     */
    private function arrangeAll(array $products): array
    {
        $featured = [];
        $rest = [];
        foreach ($products as $product) {
            $position = array_search($product->getName(), self::ALL_FEATURED_ROW, true);
            if (false === $position) {
                $rest[] = $product;
            } else {
                $featured[$position] = $product;
            }
        }
        ksort($featured);

        return [...rray_slice($rest, 0, self::ALL_LEADING_COUNT), ...$featured];
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
     * The storefront's stock badge for one product, keyed by product id. This is what the live
     * stock endpoint sends, built from the same rules as the cards rendered on page load.
     *
     * @return array<int, array{stock: int, level: string, status: string, available: bool}>
     */
    public function stockSnapshot(): array
    {
        $snapshot = [];
        foreach ($this->products->findStockLevels() as $id => $stock) {
            $snapshot[$id] = ['stock' => $stock] + self::describeStock($stock) + ['available' => $stock > 0];
        }

        return $snapshot;
    }

    /**
     * @return array{level: string, status: string}
     */
    public static function describeStock(int $stock): array
    {
        if ($stock <= 0) {
            return ['level' => 'out', 'status' => 'Out of Stock'];
        }

        if ($stock <= Product::LOW_STOCK_THRESHOLD) {
            return ['level' => 'low', 'status' => sprintf('Only %d left', $stock)];
        }

        return ['level' => 'ok', 'status' => sprintf('%d in stock', $stock)];
    }

    /**
     * @return array<string, mixed>
     */
    public function hydrate(Product $product): array
    {
        $stock = $product->getStock();
        ['level' => $level, 'status' => $status] = self::describeStock($stock);

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
