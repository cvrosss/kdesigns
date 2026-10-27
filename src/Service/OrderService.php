<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Order placement and status changes. Stock is reserved when an order is placed and returned
 * when it is cancelled, always in the same transaction as the order row.
 */
final class OrderService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProductRepository $products,
        private readonly OrderRepository $orders,
    ) {
    }

    /**
     * Price, name and image are copied from the product, never from the request.
     *
     * @throws InsufficientStockException when the requested quantity is not in stock
     */
    public function place(User $user, Product $product, int $quantity, Order $order): Order
    {
        $quantity = max(1, min(50, $quantity));

        $order->setUser($user)
            ->setProduct($product)
            ->setProductName($product->getName())
            ->setUnitPrice($product->getPrice())
            ->setProductImage($product->getImage())
            ->setQuantity($quantity)
            ->setStatus(Order::STATUS_PENDING)
            ->setReference($this->newReference());

        try {
            $this->em->getConnection()->transactional(function () use ($product, $quantity, $order): void {
                if (!$this->products->reserve($product, $quantity)) {
                    throw new InsufficientStockException($product->getName(), $quantity, 0);
                }
                $this->em->persist($order);
                $this->em->flush();
            });
        } catch (InsufficientStockException) {
            $this->em->refresh($product);
            throw new InsufficientStockException($product->getName(), $quantity, $product->getStock());
        }

        return $order;
    }

    /**
     * Cancelling returns the reserved units; un-cancelling takes them again.
     *
     * @throws \InvalidArgumentException for an unknown status
     * @throws InsufficientStockException when reinstating an order the stock can no longer cover
     */
    public function updateStatus(Order $order, string $status): void
    {
        if (!\in_array($status, $order->allowedStatuses(), true)) {
            throw new \InvalidArgumentException('Unknown order status.');
        }

        $previous = $order->getStatus();
        if ($previous === $status) {
            return;
        }

        $product = $order->getProduct();
        $qty = $order->getQuantity();

        try {
            $this->em->getConnection()->transactional(function () use ($order, $product, $qty, $previous, $status): void {
                if (null !== $product) {
                    if (Order::STATUS_CANCELLED !== $previous && Order::STATUS_CANCELLED === $status) {
                        $this->products->release($product, $qty);
                    } elseif (Order::STATUS_CANCELLED === $previous && Order::STATUS_CANCELLED !== $status) {
                        if (!$this->products->reserve($product, $qty)) {
                            throw new InsufficientStockException($product->getName(), $qty, 0);
                        }
                    }
                }

                $order->setStatus($status);
                $this->em->flush();
            });
        } catch (InsufficientStockException) {
            $this->em->refresh($product);
            throw new InsufficientStockException($product->getName(), $qty, $product->getStock());
        }

        if (null !== $product) {
            $this->em->refresh($product);
        }
    }

    /**
     * Adds $qty units to the product's current stock (atomically) and returns the new total.
     *
     * @throws \InvalidArgumentException when $qty is not positive or would exceed the stock ceiling
     */
    public function restock(Product $product, int $qty): int
    {
        if ($qty < 1 || !$this->products->restock($product, $qty)) {
            throw new \InvalidArgumentException('Invalid restock quantity.');
        }

        $this->em->refresh($product);

        return $product->getStock();
    }

    private function newReference(): string
    {
        do {
            $reference = 'KDB-'.random_int(200000, 299999);
        } while (null !== $this->orders->findOneBy(['reference' => $reference]));

        return $reference;
    }
}
