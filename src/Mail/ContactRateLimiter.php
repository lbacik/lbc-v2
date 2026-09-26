<?php

declare(strict_types=1);

namespace App\Mail;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Fixed-window application rate limiter backed by the shared cache pool.
 */
final class ContactRateLimiter
{
    public function __construct(
        private readonly CacheItemPoolInterface $pool,
        private readonly int $limit = 5,
        private readonly int $windowSeconds = 3600,
    ) {
        if ($limit < 1) {
            throw new \InvalidArgumentException('Rate limit must be at least 1.');
        }

        if ($windowSeconds < 1) {
            throw new \InvalidArgumentException('Rate limit window must be at least 1 second.');
        }
    }

    public function isAllowed(string $identifier): bool
    {
        $item = $this->pool->getItem('contact_rl_'.sha1($identifier));

        if (!$item->isHit()) {
            $item->set(1);
            $item->expiresAfter($this->windowSeconds);
            $this->pool->save($item);

            return true;
        }

        $count = (int) $item->get();
        if ($count >= $this->limit) {
            return false;
        }

        $item->set($count + 1);
        $this->pool->save($item);

        return true;
    }
}
