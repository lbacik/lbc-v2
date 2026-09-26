<?php

declare(strict_types=1);

namespace App\Tests\Support\Mail;

use Psr\Log\AbstractLogger;

final class CollectingLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string, context: array<string, mixed>}> */
    public array $records = [];

    public function log($level, $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
    }

    public function dumpedRecords(): string
    {
        return print_r($this->records, true);
    }
}
