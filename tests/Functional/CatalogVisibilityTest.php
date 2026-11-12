<?php

namespace App\Tests\Functional;

use App\Tests\ShopWebTestCase;

/**
 * Regression test for the bug where the catalog showed a curated slice of the shop, so a
 * product staff had just added was invisible — it sorted last and fell outside the slice.
 */
final class CatalogVisibilityTest extends ShopWebTestCase
{
    public function testCatalogListsEveryProduct(): void
    {
        $category = $this->makeCategory();
        for ($i = 1; $i <= 15; ++$i) {
            $this->makeProduct('Bouquet '.$i, $category, sortOrder: $i);
        }

        $crawler = $this->client->request('GET', '/catalog');

        self::assertResponseIsSuccessful();
        self::assertCount(15, $crawler->filter('.product-card'), 'The catalog must list every product.');
    }

    public function testANewlyAddedProductAppearsInTheCatalog(): void
    {
        $category = $this->makeCategory();
        for ($i = 1; $i <= 15; ++$i) {
            $this->makeProduct('Bouquet '.$i, $category, sortOrder: $i);
        }

        // A product added later sorts after every existing one, which is exactly the case
        // the old curated slice dropped.
        $this->makeProduct('Brand New Bloom', $category, sortOrder: 99);

        $this->client->request('GET', '/catalog');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Brand New Bloom', (string) $this->client->getResponse()->getContent());
    }

    public function testCategoryFilterShowsOnlyThatCategory(): void
    {
        $fresh = $this->makeCategory('Fresh Bouquet');
        $dried = $this->makeCategory('Dried Bouquet');
        $this->makeProduct('Fresh One', $fresh, sortOrder: 1);
        $this->makeProduct('Fresh Two', $fresh, sortOrder: 2);
        $this->makeProduct('Dried One', $dried, sortOrder: 3);

        $crawler = $this->client->request('GET', '/catalog?category='.$fresh->getSlug());

        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('.product-card'));
        self::assertStringNotContainsString('Dried One', (string) $this->client->getResponse()->getContent());
    }

    /** An unknown filter falls back to the full catalog rather than erroring or showing nothing. */
    public function testUnknownCategoryFilterFallsBackToEverything(): void
    {
        $category = $this->makeCategory();
        $this->makeProduct('Only Bloom', $category, sortOrder: 1);

        $crawler = $this->client->request('GET', '/catalog?category=does-not-exist');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('.product-card'));
    }
}
