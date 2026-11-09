<?php

namespace App\Service;

/** Thrown when the catalog cannot cover a requested quantity. */
final class InsufficientStockException extends \RuntimeException
{
    public function __construct(
        public readonly string $productName,
        public readonly int $requested,
        public readonly int $available,
    ) {
        parent::__construct(sprintf('Only %d of "%s" left — %d requested.', $available, $productName, $requested));
    }
}
