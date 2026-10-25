<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    /** @return list<Order> newest first */
    public function findForUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['createdAt' => 'DESC', 'id' => 'DESC']);
    }

    /** @return list<Order> newest first, with buyers loaded */
    public function findAllWithUser(?int $limit = null): array
    {
        return $this->createQueryBuilder('o')
            ->addSelect('u')
            ->join('o.user', 'u')
            ->orderBy('o.createdAt', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return array<string, int> status => number of orders (every status present) */
    public function countByStatus(): array
    {
        $rows = $this->createQueryBuilder('o')
            ->select('o.status AS status, COUNT(o.id) AS n')
            ->groupBy('o.status')
            ->getQuery()
            ->getArrayResult();

        $counts = array_fill_keys(Order::STATUSES, 0);
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['n'];
        }

        return $counts;
    }

    /** Money taken: cancelled orders are excluded. */
    public function revenue(): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COALESCE(SUM(o.unitPrice * o.quantity), 0)')
            ->andWhere('o.status <> :cancelled')
            ->setParameter('cancelled', Order::STATUS_CANCELLED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countBuyers(): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(DISTINCT IDENTITY(o.user))')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Per-buyer roll-up for the admin "Buyers History" tab, biggest spender first.
     *
     * @return list<array{name: string, email: string, order_count: int, total_spent: int, last_order: \DateTimeImmutable, items: list<string>}>
     */
    public function buyersSummary(): array
    {
        $rows = $this->createQueryBuilder('o')
            ->select('u.id AS id, u.name AS name, u.email AS email')
            ->addSelect('COUNT(o.id) AS order_count')
            ->addSelect('COALESCE(SUM(CASE WHEN o.status = :cancelled THEN 0 ELSE o.unitPrice * o.quantity END), 0) AS total_spent')
            ->addSelect('MAX(o.createdAt) AS last_order')
            ->join('o.user', 'u')
            ->groupBy('u.id, u.name, u.email')
            ->orderBy('total_spent', 'DESC')
            ->setParameter('cancelled', Order::STATUS_CANCELLED)
            ->getQuery()
            ->getArrayResult();

        $items = [];
        foreach ($this->findAllWithUser() as $order) {
            $items[$order->getUser()->getId()][] = $order->getProductName();
        }

        return array_map(static fn (array $r) => [
            'name' => $r['name'],
            'email' => $r['email'],
            'order_count' => (int) $r['order_count'],
            'total_spent' => (int) $r['total_spent'],
            'last_order' => new \DateTimeImmutable($r['last_order']),
            'items' => array_values(array_unique($items[$r['id']] ?? [])),
        ], $rows);
    }
}
