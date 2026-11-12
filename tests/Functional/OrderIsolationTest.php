<?php

namespace App\Tests\Functional;

use App\Tests\ShopWebTestCase;

/**
 * A customer must only ever see their own orders, through the page and the JSON endpoint alike.
 */
final class OrderIsolationTest extends ShopWebTestCase
{
    public function testTheOrdersEndpointReturnsOnlyTheSignedInCustomersOrders(): void
    {
        $category = $this->makeCategory();
        $product = $this->makeProduct('Peony Bouquet', $category);

        $alice = $this->makeUser('alice@example.test');
        $bob = $this->makeUser('bob@example.test');
        $this->makeOrder($alice, $product, 'KDB-200001');
        $this->makeOrder($bob, $product, 'KDB-200002');
        $this->makeOrder($bob, $product, 'KDB-200003');

        $this->login($alice);
        $this->client->request('GET', '/orders', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $this->client->getResponse()->getContent(), true);

        self::assertCount(1, $payload['orders']);
        self::assertSame('KDB-200001', $payload['orders'][0]['reference']);
    }

    public function testOrderHistoryPageShowsOnlyOwnOrders(): void
    {
        $category = $this->makeCategory();
        $product = $this->makeProduct('Peony Bouquet', $category);

        $alice = $this->makeUser('alice@example.test');
        $bob = $this->makeUser('bob@example.test');
        $this->makeOrder($alice, $product, 'KDB-200001');
        $this->makeOrder($bob, $product, 'KDB-200002');

        $this->login($alice);
        $this->client->request('GET', '/my-orders');

        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('KDB-200001', $html);
        self::assertStringNotContainsString('KDB-200002', $html);
    }

    public function testAnonymousVisitorsGetNoOrders(): void
    {
        $this->client->request('GET', '/orders', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseIsSuccessful();
        self::assertSame(['orders' => []], json_decode((string) $this->client->getResponse()->getContent(), true));
    }

    public function testAnonymousVisitorsCannotPlaceAnOrder(): void
    {
        $category = $this->makeCategory();
        $product = $this->makeProduct('Peony Bouquet', $category);

        $this->client->request(
            'POST',
            '/orders',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['productId' => $product->getId(), 'quantity' => 1]),
        );

        self::assertResponseStatusCodeSame(401);
    }
}
