<?php

declare(strict_types=1);

namespace App\Tests\Smoke;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpClient;

/**
 * HTTP smoke test against the deployed contact form.
 *
 * Submits the form the way the frontend does (GET the page, extract the
 * server-side CSRF token, POST the fields) and checks acceptance semantics:
 * - no legacy static token is exposed in the page,
 * - an accepted send answers 202 with body "OK",
 * - a rejected send is generic and names the alternative contact route.
 *
 * Runs only when CONTACT_SMOKE_BASE_URL is set (e.g. against production in
 * validation mode with CONTACT_SMOKE_VALIDATION_TOKEN for the operator
 * header). Otherwise skipped so the default suite stays hermetic.
 *
 * @group smoke
 */
class ContactFormSmokeTest extends TestCase
{
    public function testDeployedFormAcceptanceSemantics(): void
    {
        $baseUrl = (string) getenv('CONTACT_SMOKE_BASE_URL');
        if ('' === $baseUrl) {
            self::markTestSkipped('CONTACT_SMOKE_BASE_URL is not set.');
        }

        $client = HttpClient::create();
        $baseUrl = rtrim($baseUrl, '/');

        $page = $client->request('GET', $baseUrl.'/')->getContent();
        self::assertStringNotContainsString('109455adfauuiidf3343434', $page, 'Legacy static token must not be exposed.');

        if (1 !== preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $page, $matches)) {
            self::fail('Deployed form does not expose a server-side CSRF token.');
        }

        $headers = [];
        $validationToken = (string) getenv('CONTACT_SMOKE_VALIDATION_TOKEN');
        if ('' !== $validationToken) {
            $headers['X-Contact-Validation-Token'] = $validationToken;
        }

        $response = $client->request('POST', $baseUrl.'/contact', [
            'headers' => $headers,
            'body' => [
                'name' => 'Smoke Check',
                'email' => 'smoke@example.com',
                'subject' => 'Smoke test — please ignore',
                'message' => 'Automated smoke submission; no action needed.',
                'recaptcha-response' => 'smoke',
                '_csrf_token' => $matches[1],
            ],
        ]);

        // reCAPTCHA cannot pass from a smoke client, so the deployed form must
        // reject generically (422) — or accept (202) in environments where the
        // check is bypassed. Either way the contract below must hold.
        $status = $response->getStatusCode();
        self::assertContains($status, [202, 422, 429, 503], 'Unexpected smoke status: '.$status);

        $body = $response->getContent(false);
        if (202 === $status) {
            self::assertSame('OK', trim($body), 'Acceptance must use the stable OK semantics.');
        } else {
            self::assertStringContainsString('lukasz@lukaszbacik.com', $body, 'Rejections must name the alternative contact route.');
            self::assertStringNotContainsString('smoke@example.com', $body, 'Rejections must not echo PII.');
        }
    }
}
