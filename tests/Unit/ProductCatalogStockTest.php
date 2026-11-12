<?php

namespace App\Tests\Unit;

use App\Entity\Product;
use App\Service\ProductCatalog;
use PHPUnit\Framework\TestCase;

/**
 * The stock badge the storefront shows, and the live /stock endpoint, are built from this
 * one function — so the card and the poll can never disagree.
 */
final class ProductCatalogStockTest extends TestCase
{
    public function testSoldOut(): void
    {
        self::assertSame(
            ['level' => 'out', 'status' => 'Out of Stock'],
            ProductCatalog::describeStock(0),
        );
    }

    public function testNegativeStockIsStillSoldOut(): void
    {
        self::assertSame('out', ProductCatalog::describeStock(-3)['level']);
    }

    public function testLowStockNamesTheExactCount(): void
    {
        $described = ProductCatalog::describeStock(2);

        self::assertSame('low', $described['level']);
        self::assertSame('Only 2 left', $described['status']);
    }

    public function testTheThresholdItselfCountsAsLow(): void
    {
        self::assertSame('low', ProductCatalog::describeStock(Product::LOW_STOCK_THRESHOLD)['level']);
    }

    public function testOneAboveTheThresholdIsNormalStock(): void
    {
        $described = ProductCatalog::describeStock(Product::LOW_STOCK_THRESHOLD + 1);

        self::assertSame('ok', $described['level']);
        self::assertSame(sprintf('%d in stock', Product::LOW_STOCK_THRESHOLD + 1), $described['status']);
    }
}
