<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Stores an uploaded product photo under public/images/products and returns the path relative
 * to /public, the same shape as the seeded products (images/products/foo.jpg).
 */
final class ProductImageUploader
{
    public const DIRECTORY = 'images/products';

    public function __construct(
        private readonly SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDir,
    ) {
    }

    public function upload(UploadedFile $file, string $productName): string
    {
        // The name and extension are built here; nothing from the client's filename is trusted.
        $filename = sprintf(
            '%s-%s.%s',
            strtolower($this->slugger->slug($productName)->toString()) ?: 'product',
            bin2hex(random_bytes(4)),
            $file->guessExtension() ?? 'jpg',
        );

        $file->move($this->publicDir.'/'.self::DIRECTORY, $filename);

        return self::DIRECTORY.'/'.$filename;
    }

    /**
     * Deletes a stored photo once nothing points at it any more — called when a product is
     * deleted or its photo replaced, so uploads do not pile up on disk forever.
     *
     * Only paths inside images/products are touched, and a missing file is not an error:
     * the caller has already committed the database change and must not fail because of this.
     */
    public function remove(?string $path): void
    {
        if (null === $path || !str_starts_with($path, self::DIRECTORY.'/')) {
            return;
        }

        // Reject anything that tries to climb out of the directory.
        $filename = basename($path);
        if ('' === $filename || self::DIRECTORY.'/'.$filename !== $path) {
            return;
        }

        $file = $this->publicDir.'/'.self::DIRECTORY.'/'.$filename;
        if (is_file($file)) {
            @unlink($file);
        }
    }
}
