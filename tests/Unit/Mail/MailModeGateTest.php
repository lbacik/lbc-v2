<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mail;

use App\Mail\MailMode;
use App\Mail\MailModeGate;
use App\Mail\MailNotAllowedException;
use PHPUnit\Framework\TestCase;

class MailModeGateTest extends TestCase
{
    public function testDefaultModeIsDisabled(): void
    {
        $gate = new MailModeGate('disabled');

        self::assertSame(MailMode::Disabled, $gate->mode());
        self::assertFalse($gate->requiresAudit());

        try {
            $gate->assertSendAllowed(null, new \DateTimeImmutable('2026-09-26 12:00:00'));
            self::fail('Expected MailNotAllowedException');
        } catch (MailNotAllowedException $e) {
            self::assertSame('disabled', $e->getReason());
        }
    }

    public function testEnabledModeAllowsWithoutToken(): void
    {
        $gate = new MailModeGate('enabled');

        $gate->assertSendAllowed(null, new \DateTimeImmutable('2026-09-26 12:00:00'));

        self::assertFalse($gate->requiresAudit());
    }

    public function testValidationModeAllowsWithTokenInsideWindow(): void
    {
        $gate = new MailModeGate('validation', '2026-09-27 12:00:00', 'op-secret');

        $gate->assertSendAllowed('op-secret', new \DateTimeImmutable('2026-09-26 12:00:00'));

        self::assertTrue($gate->requiresAudit());
    }

    public function testValidationModeRejectsWrongToken(): void
    {
        $gate = new MailModeGate('validation', '2026-09-27 12:00:00', 'op-secret');

        try {
            $gate->assertSendAllowed('wrong', new \DateTimeImmutable('2026-09-26 12:00:00'));
            self::fail('Expected MailNotAllowedException');
        } catch (MailNotAllowedException $e) {
            self::assertSame('forbidden', $e->getReason());
        }
    }

    public function testValidationModeRejectsMissingToken(): void
    {
        $gate = new MailModeGate('validation', '2026-09-27 12:00:00', 'op-secret');

        try {
            $gate->assertSendAllowed(null, new \DateTimeImmutable('2026-09-26 12:00:00'));
            self::fail('Expected MailNotAllowedException');
        } catch (MailNotAllowedException $e) {
            self::assertSame('forbidden', $e->getReason());
        }
    }

    public function testValidationModeRejectsExpiredWindow(): void
    {
        $gate = new MailModeGate('validation', '2026-09-25 12:00:00', 'op-secret');

        try {
            $gate->assertSendAllowed('op-secret', new \DateTimeImmutable('2026-09-26 12:00:00'));
            self::fail('Expected MailNotAllowedException');
        } catch (MailNotAllowedException $e) {
            self::assertSame('expired', $e->getReason());
        }
    }

    public function testUnknownModeIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new MailModeGate('sometimes');
    }

    public function testValidationModeWithoutTokenIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new MailModeGate('validation', '2026-09-27 12:00:00', '');
    }

    public function testValidationModeWithoutWindowIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new MailModeGate('validation', '', 'op-secret');
    }

    public function testValidationModeWithGarbageWindowIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new MailModeGate('validation', 'not-a-date', 'op-secret');
    }
}
