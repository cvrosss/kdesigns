<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * An empty APP_SECRET silently weakens every signed value the firewall issues — the
     * remember-me cookie above all, which stays valid for a week. Production refuses to
     * start without one rather than running on a key anybody could guess.
     */
    public function boot(): void
    {
        if ('prod' === $this->getEnvironment() && '' === trim((string) ($_SERVER['APP_SECRET'] ?? $_ENV['APP_SECRET'] ?? ''))) {
            throw new \RuntimeException('APP_SECRET is empty. Set it in .env.local, .env.docker, or as a real environment variable before running in production. Generate one with: php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"');
        }

        parent::boot();
    }
}
