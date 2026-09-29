<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * The contact.send audit record must reach the prod log on every request,
 * not only on requests that also log an error. The prod "main" handler is
 * fingers_crossed at error level, so a record routed through it is dropped
 * for accepted and rejected sends alike.
 */
class ProdContactAuditLogTest extends TestCase
{
    public function testProdWritesContactAuditRecordForNonErrorResponse(): void
    {
        // A fresh cache dir: a stale local var/cache/prod must not decide the result.
        $cacheDir = sys_get_temp_dir().'/lbc-v2-prod-audit-'.bin2hex(random_bytes(4));
        $process = new Process(
            [\PHP_BINARY, __DIR__.'/../Support/Logging/handle-prod-request.php'],
            \dirname(__DIR__, 2),
            ['APP_ENV' => 'prod', 'APP_DEBUG' => '0', 'APP_CACHE_DIR' => $cacheDir],
        );
        try {
            $process->mustRun();
        } finally {
            (new Filesystem())->remove($cacheDir);
        }

        self::assertContains($process->getOutput(), ['403', '429'], 'Expected a rejected, non-error response.');

        $records = [];
        foreach (explode("\n", $process->getErrorOutput()) as $line) {
            $record = json_decode($line, true);
            if (\is_array($record) && 'contact.send' === ($record['message'] ?? null)) {
                $records[] = $record;
            }
        }

        self::assertCount(1, $records, "Expected exactly one contact.send record on stderr, got:\n".$process->getErrorOutput());
        self::assertSame('contact', $records[0]['channel']);
        self::assertSame('INFO', $records[0]['level_name']);
        self::assertSame('rejected', $records[0]['context']['outcome'] ?? null);
        self::assertArrayHasKey('audit', $records[0]['context']);
        self::assertStringNotContainsString('Audit Check', $process->getErrorOutput(), 'Logs must not contain form content.');
    }
}
