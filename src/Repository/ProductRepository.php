<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * Products in catalog display order, optionally limited to one category slug.
     *
     * @return list<Product>
     */
    public function findCatalog(?string $categorySlug = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->addSelect('c')
            ->join('p.category', 'c')
            ->orderBy('p.sortOrder', 'ASC')
            ->addOrderBy('p.id', 'ASC');

        if (null !== $categorySlug) {
            $qb->andWhere('c.slug = :slug')->setParameter('slug', $categorySlug);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Take $qty units out of stock only if that many exist. The check and the decrement are one
     * conditional UPDATE, so two shoppers racing for the last unit cannot both succeed.
     */
    public function reserve(Product $product, int $qty): bool
    {
        if ($qty <= 0) {
            return false;
        }

        $affected = $this->getEntityManager()->getConnection()->executeStatement(
            'UPDATE product SET stock = stock - :qty WHERE id = :id AND stock >= :qty',
            ['qty' => $qty, 'id' => $product->getId()],
            ['qty' => ParameterType::INTEGER, 'id' => ParameterType::INTEGER],
        );

        return $affected > 0;
    }

    /** Return $qty units to stock (cancelled order), capped at the inventory ceiling. */
    public function release(Product $product, int $qty): void
    {
        if ($qty <= 0) {
            return;
        }

        $this->getEntityManager()->getConnection()->executeStatement(
            'UPDATE product SET stock = LEAST(stock + :qty, :max) WHERE id = :id',
            ['qty' => $qty, 'max' => Product::MAX_STOCK, 'id' => $product->getId()],
            // Typed as integers: untyped, MySQL would compare LEAST() arguments as strings.
            ['qty' => ParameterType::INTEGER, 'max' => ParameterType::INTEGER, 'id' => ParameterType::INTEGER],
        );
    }

    /** Products with stock at or below $threshold (includes sold out), fewest first. */
    public function findLowStock(int $threshold, int $limit = 5): array
    {
        return $this->createQueryBuilder('p')
            ->addSelect('c')
            ->join('p.category', 'c')
            ->andWhere('p.stock <= :t')
            ->setParameter('t', $threshold)
            ->orderBy('p.stock', 'ASC')
            ->addOrderBy('p.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countLowStock(int $threshold): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.stock <= :t')
            ->setParameter('t', $threshold)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.stock > 0')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
