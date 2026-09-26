<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mail;

use App\Mail\ContactRateLimiter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class ContactRateLimiterTest extends TestCase
{
    public function testAllowsRequestsUpToLimit(): void
    {
        $limiter = new ContactRateLimiter(new ArrayAdapter(), 3, 3600);

        self::assertTrue($limiter->isAllowed('203.0.113.7'));
        self::assertTrue($limiter->isAllowed('203.0.113.7'));
        self::assertTrue($limiter->isAllowed('203.0.113.7'));
        self::assertFalse($limiter->isAllowed('203.0.113.7'));
    }

    public function testLimitsAreTrackedPerIdentifier(): void
    {
        $limiter = new ContactRateLimiter(new ArrayAdapter(), 1, 3600);

        self::assertTrue($limiter->isAllowed('203.0.113.7'));
        self::assertFalse($limiter->isAllowed('203.0.113.7'));
        self::assertTrue($limiter->isAllowed('203.0.113.8'));
    }

    public function testStateIsSharedThroughThePool(): void
    {
        $pool = new ArrayAdapter();
        $first = new ContactRateLimiter($pool, 1, 3600);
        $second = new ContactRateLimiter($pool, 1, 3600);

        self::assertTrue($first->isAllowed('203.0.113.7'));
        self::assertFalse($second->isAllowed('203.0.113.7'));
    }

    public function testInvalidConfigurationIsRejected(): void
    {
        try {
            new ContactRateLimiter(new ArrayAdapter(), 0, 3600);
            self::fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException) {
            $this->expectException(\InvalidArgumentException::class);
            new ContactRateLimiter(new ArrayAdapter(), 5, 0);
        }
    }
}
