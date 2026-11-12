<?php

namespace App\Tests;

use App\Entity\Category;
use App\Entity\Order;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Base class for the functional tests: gives each test an empty database and a few
 * helpers to put a known shop into it.
 */
abstract class ShopWebTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    public const PASSWORD = 'test-password-1234';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->truncateAll();
    }

    /** Every test starts from an empty database so results never depend on test order. */
    private function truncateAll(): void
    {
        $connection = $this->em->getConnection();
        $platform = $connection->getDatabasePlatform();

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['orders', 'feedback', 'product', 'category', '`user`'] as $table) {
            $connection->executeStatement($platform->getTruncateTableSQL($table, true));
        }
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

        $this->em->clear();
    }

    protected function makeUser(string $email, string $role = User::ROLE_CUSTOMER, bool $active = true): User
    {
        $user = (new User())->setName(ucfirst(strtok($email, '@')))->setEmail($email)->setActive($active);
        $user->setPrimaryRole($role);
        $user->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, self::PASSWORD));

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    protected function makeCategory(string $name = 'Fresh Bouquet'): Category
    {
        $category = (new Category())->setCategoryname($name)->setDescription($name.' arrangements');
        $this->em->persist($category);
        $this->em->flush();

        return $category;
    }

    protected function makeProduct(string $name, Category $category, int $stock = 10, int $sortOrder = 0, ?User $createdBy = null): Product
    {
        $product = (new Product())
            ->setName($name)
            ->setCategory($category)
            ->setDescription('A test arrangement.')
            ->setPrice(1000)
            ->setStock($stock)
            ->setImage('images/products/placeholder.jpg')
            ->setSortOrder($sortOrder)
            ->setCreatedBy($createdBy);

        $this->em->persist($product);
        $this->em->flush();

        return $product;
    }

    protected function makeOrder(User $user, Product $product, string $reference): Order
    {
        $order = (new Order())
            ->setUser($user)
            ->setProduct($product)
            ->setProductName((string) $product->getName())
            ->setUnitPrice($product->getPrice())
            ->setQuantity(1)
            ->setProductImage((string) $product->getImage())
            ->setCustomerName((string) $user->getName())
            ->setContact('09171234567')
            ->setReference($reference);

        $this->em->persist($order);
        $this->em->flush();

        return $order;
    }

    protected function login(User $user): void
    {
        $this->client->loginUser($user);
    }

    /** Asserts the status code of a plain GET, with a message naming the route. */
    protected function assertGetStatus(int $expected, string $path): void
    {
        $this->client->request('GET', $path);
        self::assertSame(
            $expected,
            $this->client->getResponse()->getStatusCode(),
            sprintf('GET %s should return %d', $path, $expected),
        );
    }
}
