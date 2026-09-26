<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mail;

use App\Mail\MailBoundary;
use PHPUnit\Framework\TestCase;

/**
 * Proves there is no fallback to the legacy SMTP/shared mail path.
 *
 * The old MAILER_DSN transport, the public static token, and the
 * my-first-configuration-set reference must stay gone; the fixed boundary
 * must be the only configuration the sender can use.
 */
class NoLegacyMailPathTest extends TestCase
{
    public function testLegacyServiceIsGone(): void
    {
        self::assertFalse(class_exists('App\Service\SendEmailService'));
    }

    public function testLegacyMailerConfigIsGone(): void
    {
        self::assertFileDoesNotExist(\dirname(__DIR__, 3).'/config/packages/mailer.yaml');
    }

    public function testNoMailerDsnReferenceRemains(): void
    {
        $hits = $this->scan(['config', 'src', 'templates', 'compose.prod.yaml'], ['MAILER_DSN']);

        self::assertSame([], $hits);
    }

    public function testNoLegacyConfigurationSetReferenceRemains(): void
    {
        $hits = $this->scan(['config', 'src', 'templates', 'compose.prod.yaml'], ['my-first-configuration-set']);

        self::assertSame([], $hits);
    }

    public function testNoStaticTokenRemains(): void
    {
        $hits = $this->scan(['config', 'src', 'templates'], ['109455adfauuiidf3343434', 'SendEmailService::TOKEN']);

        self::assertSame([], $hits);
    }

    public function testBoundaryHasExactlyOneConfigurationSet(): void
    {
        self::assertSame('lbc-first-contact-events', MailBoundary::CONFIGURATION_SET);
        self::assertSame('eu-central-1', MailBoundary::REGION);
        self::assertSame('no-reply@lukaszbacik.com', MailBoundary::FROM_ADDRESS);
    }

    /**
     * @param list<string> $paths
     * @param list<string> $needles
     *
     * @return list<string>
     */
    private function scan(array $paths, array $needles): array
    {
        $root = \dirname(__DIR__, 3);
        $hits = [];

        foreach ($paths as $path) {
            $full = $root.'/'.$path;
            if (is_file($full)) {
                $files = [$full];
            } else {
                $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($full, \FilesystemIterator::SKIP_DOTS));
                $files = [];
                foreach ($iterator as $file) {
                    if ($file->isFile()) {
                        $files[] = $file->getPathname();
                    }
                }
            }

            foreach ($files as $file) {
                $content = (string) file_get_contents($file);
                foreach ($needles as $needle) {
                    if (str_contains($content, $needle)) {
                        $hits[] = $file.':'.$needle;
                    }
                }
            }
        }

        return $hits;
    }
}
