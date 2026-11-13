<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Tests\ShopWebTestCase;

/**
 * The role boundaries the shop depends on: customers must never reach staff or admin pages,
 * and staff must never reach the admin dashboard.
 */
final class AccessControlTest extends ShopWebTestCase
{
    /** Anonymous visitors are sent to the login page, never shown the page itself. */
    public function testAnonymousIsRedirectedFromProtectedPages(): void
    {
        foreach (['/my-orders', '/account', '/manage/products', '/category', '/admin', '/admin/users'] as $path) {
            $this->assertGetStatus(302, $path);
        }
    }

    public function testPublicPagesAreOpenToEveryone(): void
    {
        foreach (['/', '/catalog', '/about', '/stock'] as $path) {
            $this->assertGetStatus(200, $path);
        }
    }

    public function testCustomerCannotReachStaffOrAdminPages(): void
    {
        $this->login($this->makeUser('customer@example.test'));

        foreach (['/manage/products', '/manage/products/new', '/category', '/category/new'] as $path) {
            $this->assertGetStatus(403, $path);
        }

        foreach (['/admin', '/admin/users', '/admin/orders', '/admin/inventory', '/admin/buyers', '/admin/feedback'] as $path) {
            $this->assertGetStatus(403, $path);
        }
    }

    /** Staff run orders and stock day to day; the dashboard, users, buyers and feedback stay admin-only. */
    public function testStaffCanReachOrdersAndInventoryButNotTheAdminOnlyPages(): void
    {
        $this->login($this->makeUser('staff@example.test', User::ROLE_STAFF));

        $this->makeCategory();
        foreach (['/manage/products', '/category', '/admin/orders', '/admin/inventory'] as $path) {
            $this->assertGetStatus(200, $path);
        }

        foreach (['/admin', '/admin/users', '/admin/buyers', '/admin/feedback'] as $path) {
            $this->assertGetStatus(403, $path);
        }
    }

    /** The order-status and restock actions are POSTs: a customer must be refused outright. */
    public function testCustomerCannotUseTheOrderAndStockActions(): void
    {
        $this->login($this->makeUser('customer@example.test'));

        foreach (['/admin/orders/KDB-200001/status', '/admin/inventory/1/stock', '/admin/inventory/new'] as $path) {
            $this->client->request('POST', $path);
            self::assertResponseStatusCodeSame(403, $path.' must be refused to a customer');
        }
    }

    public function testAdminCanReachEverything(): void
    {
        $this->login($this->makeUser('admin@example.test', User::ROLE_ADMIN));
        $this->makeCategory();

        foreach (['/admin', '/admin/users', '/admin/orders', '/admin/inventory', '/admin/buyers', '/admin/feedback', '/manage/products', '/category'] as $path) {
            $this->assertGetStatus(200, $path);
        }
    }

    /** The Account button must land on the customer's own orders, not the staff panel. */
    public function testAccountSendsACustomerToTheirOrderHistory(): void
    {
        $this->login($this->makeUser('customer@example.test'));

        $this->client->request('GET', '/account');
        self::assertResponseRedirects('/my-orders');
    }

    /**
     * Goes through the real login endpoint rather than loginUser(), because the test helper
     * installs a token directly and never consults the UserChecker that does this job.
     */
    public function testDisabledAccountCannotLogIn(): void
    {
        $this->makeUser('blocked@example.test', User::ROLE_CUSTOMER, active: false);

        $this->client->request(
            'POST',
            '/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'blocked@example.test', 'password' => self::PASSWORD]),
        );

        self::assertResponseStatusCodeSame(401);
        self::assertStringContainsString('disabled', (string) $this->client->getResponse()->getContent());
    }

    public function testAnActiveAccountCanLogIn(): void
    {
        $this->makeUser('active@example.test');

        $this->client->request(
            'POST',
            '/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'active@example.test', 'password' => self::PASSWORD]),
        );

        self::assertResponseIsSuccessful();
    }
}
