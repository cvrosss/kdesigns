<?php

namespace App\Tests\Functional;

use App\Entity\Category;
use App\Entity\Order;
use App\Entity\User;
use App\Tests\ShopWebTestCase;

/**
 * Regression tests for the two ways the shop used to lose or corrupt data: a duplicate
 * category name crashed with a constraint violation, and deleting a buyer took their
 * whole order history with them.
 */
final class DataIntegrityTest extends ShopWebTestCase
{
    public function testDuplicateCategoryNameIsRejectedAsAFormErrorNotACrash(): void
    {
        $this->login($this->makeUser('staff@example.test', User::ROLE_STAFF));
        $this->makeCategory('Fresh Bouquet');

        $crawler = $this->client->request('GET', '/category/new');
        $form = $crawler->selectButton('Create category')->form([
            'category[categoryname]' => 'Fresh Bouquet',
            'category[description]' => 'A duplicate on purpose.',
        ]);
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422, 'A duplicate name must be a validation error, not a 500.');
        self::assertStringContainsString(
            'already exists',
            (string) $this->client->getResponse()->getContent(),
        );
        self::assertSame(1, $this->em->getRepository(Category::class)->count([]));
    }

    public function testDeletingABuyerIsRefusedSoTheirOrdersSurvive(): void
    {
        $admin = $this->makeUser('admin@example.test', User::ROLE_ADMIN);
        $buyer = $this->makeUser('buyer@example.test');
        $product = $this->makeProduct('Peony Bouquet', $this->makeCategory());
        $this->makeOrder($buyer, $product, 'KDB-200001');

        $this->login($admin);
        $crawler = $this->client->request('GET', '/admin/users');
        $token = $crawler->filter(sprintf('form[action*="/admin/users/%d/delete"] input[name="_token"]', $buyer->getId()))->attr('value');

        $this->client->request('POST', sprintf('/admin/users/%d/delete', $buyer->getId()), ['_token' => $token]);

        self::assertNotNull(
            $this->em->getRepository(User::class)->find($buyer->getId()),
            'An account with orders must not be deleted.',
        );
        self::assertSame(1, $this->em->getRepository(Order::class)->count([]), 'The order history must survive.');
    }

    public function testAnAccountWithoutOrdersCanStillBeDeleted(): void
    {
        $admin = $this->makeUser('admin@example.test', User::ROLE_ADMIN);
        $spare = $this->makeUser('spare@example.test');

        $this->login($admin);
        $crawler = $this->client->request('GET', '/admin/users');
        $token = $crawler->filter(sprintf('form[action*="/admin/users/%d/delete"] input[name="_token"]', $spare->getId()))->attr('value');

        $this->client->request('POST', sprintf('/admin/users/%d/delete', $spare->getId()), ['_token' => $token]);

        self::assertNull($this->em->getRepository(User::class)->find($spare->getId()));
    }

    /** Staff may only change the products they created; seeded products have no owner. */
    public function testStaffCannotEditAProductTheyDidNotCreate(): void
    {
        $staff = $this->makeUser('staff@example.test', User::ROLE_STAFF);
        $product = $this->makeProduct('Seeded Bloom', $this->makeCategory());

        $this->login($staff);
        $this->assertGetStatus(403, '/manage/products/'.$product->getId().'/edit');
    }

    public function testStaffCanEditTheirOwnProduct(): void
    {
        $staff = $this->makeUser('staff@example.test', User::ROLE_STAFF);
        $product = $this->makeProduct('My Bloom', $this->makeCategory(), createdBy: $staff);

        $this->login($staff);
        $this->assertGetStatus(200, '/manage/products/'.$product->getId().'/edit');
    }
}
