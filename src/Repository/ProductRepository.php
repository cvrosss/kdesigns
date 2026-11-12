<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\User;
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
     * Products for the staff/admin records list: all of them, or only those created by $owner,
     * optionally narrowed by a name search and a category.
     *
     * @return list<Product>
     */
    public function searchRecords(?string $term = null, ?User $owner = null, ?int $categoryId = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->addSelect('c', 'u')
            ->join('p.category', 'c')
            ->leftJoin('p.createdBy', 'u')
            ->orderBy('p.createdAt', 'DESC')
            ->addOrderBy('p.id', 'DESC');

        if (null !== $term && '' !== $term) {
            $qb->andWhere('p.name LIKE :q OR c.categoryname LIKE :q')->setParameter('q', '%'.addcslashes($term, '%_\\').'%');
        }
        if (null !== $owner) {
            $qb->andWhere('p.createdBy = :owner')->setParameter('owner', $owner);
        }
        if (null !== $categoryId) {
            $qb->andWhere('c.id = :cat')->setParameter('cat', $categoryId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Current stock of every product, read straight from the table (no entities, no joins), so it is
     * cheap enough for the storefront to poll.
     *
     * @return array<int, int> product id => units in stock
     */
    public function findStockLevels(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.id', 'p.stock')
            ->getQuery()
            ->getArrayResult();

        return array_map('intval', array_column($rows, 'stock', 'id'));
    }

    /** Sort position that places a new product after every existing one in the catalog. */
    public function nextSortOrder(): int
    {
        return 1 + (int) $this->createQueryBuilder('p')
            ->select('MAX(p.sortOrder)')
            ->getQuery()
            ->getSingleScalarResult();
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

    /**
     * Add $qty to the stock held in the database right now (not to a value the browser saw), so
     * concurrent orders or restocks are never overwritten. Refuses to pass the inventory ceiling.
     */
    public function restock(Product $product, int $qty): bool
    {
        if ($qty <= 0) {
            return false;
        }

        $affected = $this->getEntityManager()->getConnection()->executeStatement(
            'UPDATE product SET stock = stock + :qty WHERE id = :id AND stock + :qty <= :max',
            ['qty' => $qty, 'max' => Product::MAX_STOCK, 'id' => $product->getId()],
            ['qty' => ParameterType::INTEGER, 'max' => ParameterType::INTEGER, 'id' => ParameterType::INTEGER],
        );

        return $affected > 0;
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

    /** How many products still point at this stored photo (0 means the file can be deleted). */
    public function countByImage(string $image): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.image = :image')
            ->setParameter('image', $image)
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
