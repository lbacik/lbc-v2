<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mail;

use App\Mail\RecaptchaVerification;
use App\Mail\RecaptchaVerifier;
use PHPUnit\Framework\TestCase;
use ReCaptcha\ReCaptcha;
use ReCaptcha\Response;

class RecaptchaVerifierTest extends TestCase
{
    public function testSuccessPassesThrough(): void
    {
        $verifier = new RecaptchaVerifier($this->recaptcha(new Response(true)));

        $result = $verifier->verify('token', '203.0.113.7');

        self::assertTrue($result->ok);
        self::assertSame('ok', $result->reason);
    }

    public function testMissingTokenMapsToSafeReason(): void
    {
        $verifier = new RecaptchaVerifier($this->recaptcha(new Response(false, [ReCaptcha::E_MISSING_INPUT_RESPONSE])));

        $result = $verifier->verify(null, null);

        self::assertFalse($result->ok);
        self::assertSame('missing-token', $result->reason);
    }

    public function testLowScoreMapsToSafeReason(): void
    {
        $verifier = new RecaptchaVerifier($this->recaptcha(new Response(false, [ReCaptcha::E_SCORE_THRESHOLD_NOT_MET])));

        $result = $verifier->verify('token', '203.0.113.7');

        self::assertFalse($result->ok);
        self::assertSame('low-score', $result->reason);
    }

    public function testHostnameMismatchMapsToSafeReason(): void
    {
        $verifier = new RecaptchaVerifier($this->recaptcha(new Response(false, [ReCaptcha::E_HOSTNAME_MISMATCH])));

        $result = $verifier->verify('token', '203.0.113.7');

        self::assertFalse($result->ok);
        self::assertSame('hostname-mismatch', $result->reason);
    }

    public function testGenericFailureMapsToSafeReason(): void
    {
        $verifier = new RecaptchaVerifier($this->recaptcha(new Response(false, ['bad-response'])));

        $result = $verifier->verify('token', '203.0.113.7');

        self::assertFalse($result->ok);
        self::assertSame('verification-failed', $result->reason);
    }

    public function testVerifierConfiguresExpectedActionHostnameAndThreshold(): void
    {
        $recaptcha = $this->createMock(ReCaptcha::class);
        $recaptcha->expects($this->once())->method('setExpectedAction')->with('php_email_form_submit');
        $recaptcha->expects($this->once())->method('setExpectedHostname')->with('lukaszbacik.com');
        $recaptcha->expects($this->once())->method('setScoreThreshold')->with(0.5);

        new RecaptchaVerifier($recaptcha, 'php_email_form_submit', 'lukaszbacik.com', 0.5);
    }

    public function testEmptyHostnameSkipsHostnameExpectation(): void
    {
        $recaptcha = $this->createMock(ReCaptcha::class);
        $recaptcha->expects($this->never())->method('setExpectedHostname');

        new RecaptchaVerifier($recaptcha, 'php_email_form_submit', '', 0.5);
    }

    private function recaptcha(Response $response): ReCaptcha
    {
        $recaptcha = $this->createMock(ReCaptcha::class);
        $recaptcha->method('verify')->willReturn($response);

        return $recaptcha;
    }
}
