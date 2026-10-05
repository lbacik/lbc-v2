<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\PortfolioService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class PortfolioServiceTest extends TestCase
{
    public function testFlaggedItemsAreMarkedUnavailable(): void
    {
        $service = new PortfolioService(new NullLogger());
        $bySlug = array_column($service->getItems(), null, 'slug');

        self::assertTrue($bySlug['glife']['unavailable']);
        self::assertTrue($bySlug['fortune']['unavailable']);
        self::assertTrue($service->getDetails('glife')[6]);
        self::assertTrue($service->getDetails('fortune')[6]);
    }

    public function testUnflaggedItemsAreAvailable(): void
    {
        $service = new PortfolioService(new NullLogger());

        foreach ($service->getItems() as $item) {
            if (!\in_array($item['slug'], ['glife', 'fortune'], true)) {
                self::assertFalse($item['unavailable'], $item['slug']);
            }
        }
        self::assertFalse($service->getDetails('lfortune')[6]);
        self::assertFalse($service->getDetails('jsonhub')[6]);
    }
}
