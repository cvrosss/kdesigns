<?php

namespace App\Service;

use App\Repository\FeedbackRepository;

/**
 * Published customer reviews for the landing-page testimonial wall.
 */
final class TestimonialProvider
{
    public function __construct(private readonly FeedbackRepository $feedback)
    {
    }

    /**
     * @return list<array{author: string, year: int, quote: string, initial: string}>
     */
    public function findAll(int $limit = 9): array
    {
        return array_map(
            static fn ($f) => [
                'author' => $f->getName(),
                'year' => (int) $f->getCreatedAt()->format('Y'),
                'quote' => $f->getMessage(),
                'initial' => mb_strtoupper(mb_substr($f->getName(), 0, 1)),
            ],
            $this->feedback->findPublished($limit),
        );
    }
}
