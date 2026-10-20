<?php

namespace App\Service;

/**
 * Source of storefront products. Sample data lives here (one place) until a
 * database-backed repository replaces it; controllers only depend on this API.
 */
final class ProductCatalog
{
    /** Stock at or below this count is shown as "Only N left". */
    private const LOW_STOCK_THRESHOLD = 3;

    /** slug => label */
    public const CATEGORIES = [
        'fresh-bouquet' => 'Fresh Bouquet',
        'dried-bouquet' => 'Dried Bouquet',
        'bloom-box' => 'Bloom Box',
        'glass-dome' => 'Glass Dome',
        'others' => 'Others',
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function findAll(?string $categorySlug = null): array
    {
        $products = array_map($this->hydrate(...), $this->rawProducts());

        if (null === $categorySlug || !isset(self::CATEGORIES[$categorySlug])) {
            return $products;
        }

        $label = self::CATEGORIES[$categorySlug];

        return array_values(array_filter($products, static fn (array $p) => $p['category'] === $label));
    }

    /**
     * @return array<string, string> slug => label
     */
    public function getCategories(): array
    {
        return self::CATEGORIES;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $stock = $row['stock'];

        if ($stock <= 0) {
            $level = 'out';
            $status = 'Out of Stock';
        } elseif ($stock <= self::LOW_STOCK_THRESHOLD) {
            $level = 'low';
            $status = sprintf('Only %d left', $stock);
        } else {
            $level = 'ok';
            $status = sprintf('%d in stock', $stock);
        }

        return $row + [
            'stockStatus' => $status,
            'stockLevel' => $level,
            'isAvailable' => $stock > 0,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rawProducts(): array
    {
        $generic = 'A curated seasonal arrangement in a signature KDesigns presentation.';

        return [
            ['id' => 1, 'name' => 'Crimson Romance Bouquet', 'category' => 'Fresh Bouquet', 'description' => "Lush red roses with baby's breath and eucalyptus, hand-tied with silk ribbon.", 'price' => 1850, 'stock' => 12, 'badge' => 'Bestseller', 'image' => 'images/products/crimson-romance-bouquet.jpg'],
            ['id' => 2, 'name' => 'Pastel Peony Bouquet', 'category' => 'Fresh Bouquet', 'description' => 'Soft blush peonies and garden roses in a dreamy pastel palette.', 'price' => 2200, 'stock' => 5, 'badge' => 'Limited', 'image' => 'images/products/pastel-peony-bouquet.jpg'],
            ['id' => 3, 'name' => 'Sunflower & Wildflower Mix', 'category' => 'Fresh Bouquet', 'description' => 'Cheerful sunflowers paired with seasonal wildflowers and greenery.', 'price' => 1200, 'stock' => 20, 'badge' => null, 'image' => 'images/products/sunflower-wildflower-mix.jpg'],
            ['id' => 4, 'name' => 'Orchid Elegance Vase', 'category' => 'Fresh Bouquet', 'description' => 'Cascading white orchids in a handcrafted ceramic vase — a statement piece.', 'price' => 3500, 'stock' => 3, 'badge' => 'Luxury', 'image' => 'images/products/orchid-elegance-vase.jpg'],
            ['id' => 5, 'name' => 'Garden Table Centerpiece', 'category' => 'Dried Bouquet', 'description' => 'Lush garden-style centerpiece with seasonal blooms, foliage, and dried botanicals.', 'price' => 2800, 'stock' => 8, 'badge' => null, 'image' => 'images/products/garden-table-centerpiece.jpg'],
            ['id' => 6, 'name' => 'Lavender Dreams Bundle', 'category' => 'Dried Bouquet', 'description' => 'Fragrant dried lavender bundles, perfect for home décor or gifts.', 'price' => 980, 'stock' => 15, 'badge' => null, 'image' => 'images/products/lavender-dreams-bundle.jpg'],
            ['id' => 7, 'name' => 'Tropical Bloom Arrangement', 'category' => 'Dried Bouquet', 'description' => 'Bold heliconias, anthuriums, and tropical foliage in an architectural composition.', 'price' => 3200, 'stock' => 4, 'badge' => 'New', 'image' => 'images/products/tropical-bloom-arrangement.jpg'],
            ['id' => 8, 'name' => 'Bridal White Cascade', 'category' => 'Dried Bouquet', 'description' => 'All-white cascading bridal bouquet with garden roses, stephanotis, and lily of the valley.', 'price' => 4800, 'stock' => 2, 'badge' => 'Luxury', 'image' => 'images/products/bridal-white-cascade.jpg'],
            ['id' => 9, 'name' => 'Autumn Harvest Wreath', 'category' => 'Dried Bouquet', 'description' => 'Dried autumn botanicals including protea, cotton, and seed pods on a natural base.', 'price' => 1600, 'stock' => 0, 'badge' => null, 'image' => 'images/products/autumn-harvest-wreath.jpg'],
            ['id' => 10, 'name' => 'Bloom Arrangement No. 10', 'category' => 'Bloom Box', 'description' => $generic, 'price' => 1400, 'stock' => 10, 'badge' => null, 'image' => 'images/products/bloom-arrangement-10.jpg'],
            ['id' => 11, 'name' => 'Bloom Arrangement No. 11', 'category' => 'Others', 'description' => $generic, 'price' => 1400, 'stock' => 10, 'badge' => null, 'image' => 'images/products/bloom-arrangement-11.jpg'],
            ['id' => 12, 'name' => 'Bloom Arrangement No. 12', 'category' => 'Glass Dome', 'description' => $generic, 'price' => 1400, 'stock' => 10, 'badge' => null, 'image' => 'images/products/bloom-arrangement-12.jpg'],
        ];
    }
}
