<?php

namespace App\Repository;

use App\Entity\Feedback;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Feedback>
 */
class FeedbackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Feedback::class);
    }

    /** @return list<Feedback> latest published reviews, newest first */
    public function findPublished(int $limit = 6): array
    {
        return $this->findBy(['status' => Feedback::STATUS_PUBLISHED], ['createdAt' => 'DESC', 'id' => 'DESC'], $limit);
    }

    /** @return list<Feedback> */
    public function findAllNewestFirst(): array
    {
        return $this->findBy([], ['createdAt' => 'DESC', 'id' => 'DESC']);
    }
}
