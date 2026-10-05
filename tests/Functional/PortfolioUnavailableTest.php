<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PortfolioUnavailableTest extends WebTestCase
{
    public function testFlaggedDetailsPageShowsBadgeAndNoLink(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/portfolio-details/glife');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Currently unavailable', $crawler->filter('.portfolio-info')->text());
        self::assertCount(0, $crawler->filter('.portfolio-info a'));
        self::assertStringContainsString('glife.luka.sh', $crawler->filter('.portfolio-info')->text());
        self::assertStringContainsString('GLife is a web application', $crawler->filter('.portfolio-description')->text());
        self::assertGreaterThan(0, $crawler->filter('.swiper-slide img')->count());
    }

    public function testUnflaggedDetailsPageIsUnchanged(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/portfolio-details/lfortune');

        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('Currently unavailable', $crawler->filter('.portfolio-info')->text());
        self::assertSame('https://pypi.org/project/lfortune/', $crawler->filter('.portfolio-info a')->attr('href'));
    }

    public function testCardBadgeOnlyOnFlaggedEntries(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('.portfolio-item .badge-unavailable'));
        self::assertCount(1, $crawler->filter('.portfolio-item:has(a[href$="/glife"]) .badge-unavailable'));
        self::assertCount(0, $crawler->filter('.portfolio-item:has(a[href$="/lfortune"]) .badge-unavailable'));
    }
}
