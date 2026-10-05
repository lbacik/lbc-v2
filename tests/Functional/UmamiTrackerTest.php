<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class UmamiTrackerTest extends WebTestCase
{
    private const SCRIPT_URL = 'https://umami.rum.luka.sh/script.js';
    private const WEBSITE_ID = 'da20c40b-44df-4db8-bad0-8d52bbc4cc2b';

    protected function tearDown(): void
    {
        unset($_SERVER['UMAMI_SCRIPT_URL'], $_SERVER['UMAMI_WEBSITE_ID'], $_ENV['UMAMI_SCRIPT_URL'], $_ENV['UMAMI_WEBSITE_ID']);
        parent::tearDown();
    }

    public function testTrackerIsRenderedOnceInHeadWhenWebsiteIdIsSet(): void
    {
        $_SERVER['UMAMI_SCRIPT_URL'] = $_ENV['UMAMI_SCRIPT_URL'] = self::SCRIPT_URL;
        $_SERVER['UMAMI_WEBSITE_ID'] = $_ENV['UMAMI_WEBSITE_ID'] = self::WEBSITE_ID;

        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        $tags = $crawler->filter('head script[data-website-id]');
        self::assertCount(1, $tags);
        self::assertSame(self::SCRIPT_URL, $tags->attr('src'));
        self::assertSame(self::WEBSITE_ID, $tags->attr('data-website-id'));
        self::assertSame('lukaszbacik.com,www.lukaszbacik.com', $tags->attr('data-domains'));
        self::assertNotNull($tags->getNode(0)->attributes->getNamedItem('defer'));
    }

    public function testTrackerIsAbsentWhenWebsiteIdIsEmpty(): void
    {
        $_SERVER['UMAMI_SCRIPT_URL'] = $_ENV['UMAMI_SCRIPT_URL'] = self::SCRIPT_URL;
        $_SERVER['UMAMI_WEBSITE_ID'] = $_ENV['UMAMI_WEBSITE_ID'] = '';

        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('script[data-website-id]'));
        self::assertStringNotContainsString('umami', (string) $client->getResponse()->getContent());
    }
}
