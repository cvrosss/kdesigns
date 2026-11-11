<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Accounts for the admin list, newest first, optionally filtered by a name/email search and a role.
     *
     * @return list<User>
     */
    public function search(?string $term = null, ?string $role = null): array
    {
        $qb = $this->createQueryBuilder('u')->orderBy('u.createdAt', 'DESC')->addOrderBy('u.id', 'DESC');

        if (null !== $term && '' !== $term) {
            $qb->andWhere('u.name LIKE :q OR u.email LIKE :q')->setParameter('q', '%'.addcslashes($term, '%_\\').'%');
        }
        if (null !== $role) {
            $this->filterRole($qb, $role);
        }

        return $qb->getQuery()->getResult();
    }

    /** Accounts holding exactly this role (ROLE_USER means customers: neither staff nor admin). */
    public function countByRole(string $role): int
    {
        $qb = $this->createQueryBuilder('u')->select('COUNT(u.id)');
        $this->filterRole($qb, $role);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function countAll(): int
    {
        return $this->count([]);
    }

    /** Roles are stored as a JSON array, so staff/admin are matched on the quoted role name. */
    private function filterRole(QueryBuilder $qb, string $role): void
    {
        match ($role) {
            User::ROLE_ADMIN => $qb->andWhere('u.roles LIKE :admin')->setParameter('admin', '%"ROLE_ADMIN"%'),
            User::ROLE_STAFF => $qb->andWhere('u.roles LIKE :staff AND u.roles NOT LIKE :admin')
                ->setParameter('staff', '%"ROLE_STAFF"%')->setParameter('admin', '%"ROLE_ADMIN"%'),
            default => $qb->andWhere('u.roles NOT LIKE :staff AND u.roles NOT LIKE :admin')
                ->setParameter('staff', '%"ROLE_STAFF"%')->setParameter('admin', '%"ROLE_ADMIN"%'),
        };
    }
}
