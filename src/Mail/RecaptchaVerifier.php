<?php

declare(strict_types=1);

namespace App\Mail;

use ReCaptcha\ReCaptcha;

/**
 * reCAPTCHA verification with pinned expectations.
 *
 * The expected action, hostname, and score threshold come from deployment
 * configuration. Verification failures map to safe reason categories only;
 * raw error codes and tokens are never exposed to callers.
 */
final class RecaptchaVerifier
{
    public function __construct(
        private readonly ReCaptcha $reCaptcha,
        string $expectedAction = 'php_email_form_submit',
        string $expectedHostname = '',
        float $scoreThreshold = 0.5,
    ) {
        $reCaptcha->setExpectedAction($expectedAction);

        if ('' !== $expectedHostname) {
            $reCaptcha->setExpectedHostname($expectedHostname);
        }

        $reCaptcha->setScoreThreshold($scoreThreshold);
    }

    public function verify(?string $token, ?string $remoteIp): RecaptchaVerification
    {
        $response = $this->reCaptcha->verify((string) $token, $remoteIp);

        if ($response->isSuccess()) {
            return new RecaptchaVerification(true, 'ok');
        }

        $codes = $response->getErrorCodes();

        if (\in_array(ReCaptcha::E_MISSING_INPUT_RESPONSE, $codes, true)) {
            return new RecaptchaVerification(false, 'missing-token');
        }

        if (\in_array(ReCaptcha::E_SCORE_THRESHOLD_NOT_MET, $codes, true)) {
            return new RecaptchaVerification(false, 'low-score');
        }

        if (\in_array(ReCaptcha::E_HOSTNAME_MISMATCH, $codes, true)) {
            return new RecaptchaVerification(false, 'hostname-mismatch');
        }

        if (\in_array(ReCaptcha::E_ACTION_MISMATCH, $codes, true)) {
            return new RecaptchaVerification(false, 'action-mismatch');
        }

        return new RecaptchaVerification(false, 'verification-failed');
    }
}
