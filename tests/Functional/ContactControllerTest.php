<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Mail\ContactRateLimiter;
use App\Mail\MailBoundary;
use App\Mail\MailModeGate;
use App\Tests\Support\Mail\CollectingLogger;
use App\Tests\Support\Mail\FakeSesClient;
use AsyncAws\Ses\SesClient;
use ReCaptcha\ReCaptcha;
use ReCaptcha\Response;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class ContactControllerTest extends WebTestCase
{
    private const VISITOR_EMAIL = 'jane.visitor@example.com';
    private const VISITOR_MESSAGE = 'Hello, this is a strictly confidential test message body.';

    private CollectingLogger $logger;
    private FakeSesClient $ses;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = new CollectingLogger();
        $this->ses = new FakeSesClient();
    }

    public function testHomepageRendersFormWithoutLegacyToken(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('input[name="token"]'));
        self::assertStringNotContainsString('109455adfauuiidf3343434', (string) $client->getResponse()->getContent());
        self::assertGreaterThan(0, $crawler->filter('input[name="_csrf_token"]')->count());
    }

    public function testSuccessfulSubmissionIsAcceptedForDeliveryAttempt(): void
    {
        $client = $this->clientWith(recaptcha: new Response(true, [], '', '', '', 0.9, 'php_email_form_submit'));

        $client->request('POST', '/contact', $this->validPayload($client));

        self::assertSame(202, $client->getResponse()->getStatusCode());
        self::assertSame('OK', trim((string) $client->getResponse()->getContent()));

        $request = $this->ses->lastRequest;
        self::assertNotNull($request);
        self::assertSame('test-tenant', $request->getTenantName());
        self::assertSame(MailBoundary::CONFIGURATION_SET, $request->getConfigurationSetName());
        self::assertSame(MailBoundary::FROM_ADDRESS, $request->getFromEmailAddress());
        self::assertSame([self::VISITOR_EMAIL], $request->getReplyToAddresses());

        $this->assertLogContainsAcceptedWithMessageId();
        $this->assertNoPiiOrSecretsInLogs();
    }

    public function testLegacyStaticTokenIsRejectedAsUnknownField(): void
    {
        $client = $this->clientWith(recaptcha: new Response(true));

        $payload = $this->validPayload($client);
        $payload['token'] = '109455adfauuiidf3343434';
        $client->request('POST', '/contact', $payload);

        self::assertSame(422, $client->getResponse()->getStatusCode());
        self::assertSame(0, $this->ses->sendCalls);
    }

    public function testBoundaryOverrideAttemptIsRejected(): void
    {
        $client = $this->clientWith(recaptcha: new Response(true));

        $payload = $this->validPayload($client);
        $payload['configurationSet'] = 'my-first-configuration-set';
        $payload['from'] = 'attacker@evil.example';
        $client->request('POST', '/contact', $payload);

        self::assertSame(422, $client->getResponse()->getStatusCode());
        self::assertSame(0, $this->ses->sendCalls);
    }

    public function testRecaptchaFailureIsRejectedWithoutSending(): void
    {
        $client = $this->clientWith(recaptcha: new Response(false, ['bad-response']));

        $client->request('POST', '/contact', $this->validPayload($client));

        self::assertSame(422, $client->getResponse()->getStatusCode());
        self::assertSame(0, $this->ses->sendCalls);
        self::assertStringContainsString('lukasz@lukaszbacik.com', (string) $client->getResponse()->getContent());
        $this->assertNoPiiOrSecretsInLogs();
    }

    public function testRateLimitExceededReturns429(): void
    {
        $client = $this->clientWith(recaptcha: new Response(true));
        static::getContainer()->set(
            ContactRateLimiter::class,
            // Dedicated pool: the shared cache.app pool already holds hits
            // from earlier tests under the same test-client IP.
            new ContactRateLimiter(new ArrayAdapter(), 1, 3600),
        );

        $client->request('POST', '/contact', $this->validPayload($client));
        self::assertSame(202, $client->getResponse()->getStatusCode());

        $client->request('POST', '/contact', $this->validPayload($client));
        self::assertSame(429, $client->getResponse()->getStatusCode());
    }

    public function testDisabledModeReturns503WithoutSending(): void
    {
        $client = $this->clientWith(recaptcha: new Response(true));
        static::getContainer()->set(MailModeGate::class, new MailModeGate('disabled'));

        $client->request('POST', '/contact', $this->validPayload($client));

        self::assertSame(503, $client->getResponse()->getStatusCode());
        self::assertSame(0, $this->ses->sendCalls);
        self::assertStringContainsString('lukasz@lukaszbacik.com', (string) $client->getResponse()->getContent());
    }

    public function testValidationModeRequiresOperatorToken(): void
    {
        $client = $this->clientWith(recaptcha: new Response(true));
        static::getContainer()->set(
            MailModeGate::class,
            new MailModeGate('validation', (new \DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s'), 'op-secret'),
        );

        $client->request('POST', '/contact', $this->validPayload($client));
        self::assertSame(403, $client->getResponse()->getStatusCode());

        $client->request('POST', '/contact', $this->validPayload($client), [], ['HTTP_X-Contact-Validation-Token' => 'wrong']);
        self::assertSame(403, $client->getResponse()->getStatusCode());

        $client->request('POST', '/contact', $this->validPayload($client), [], ['HTTP_X-Contact-Validation-Token' => 'op-secret']);
        self::assertSame(202, $client->getResponse()->getStatusCode());
        self::assertSame(1, $this->ses->sendCalls);

        $this->assertNoPiiOrSecretsInLogs();
    }

    public function testExpiredValidationWindowReturns503(): void
    {
        $client = $this->clientWith(recaptcha: new Response(true));
        static::getContainer()->set(
            MailModeGate::class,
            new MailModeGate('validation', (new \DateTimeImmutable('-1 hour'))->format('Y-m-d H:i:s'), 'op-secret'),
        );

        $client->request('POST', '/contact', $this->validPayload($client), [], ['HTTP_X-Contact-Validation-Token' => 'op-secret']);

        self::assertSame(503, $client->getResponse()->getStatusCode());
        self::assertSame(0, $this->ses->sendCalls);
    }

    public function testSesFailureReturnsGenericErrorWithoutLeaking(): void
    {
        $client = $this->clientWith(recaptcha: new Response(true));
        $this->ses->failure = new \RuntimeException('AWS exploded: credentials test-key secret test-secret');

        $client->request('POST', '/contact', $this->validPayload($client));

        self::assertSame(502, $client->getResponse()->getStatusCode());
        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('lukasz@lukaszbacik.com', $body);
        self::assertStringNotContainsString('exploded', $body);
        self::assertStringNotContainsString(self::VISITOR_EMAIL, $body);
        self::assertStringNotContainsString(self::VISITOR_MESSAGE, $body);
        $this->assertNoPiiOrSecretsInLogs();
    }

    /**
     * @return array<string, string>
     */
    private function validPayload(KernelBrowser $client): array
    {
        $crawler = $client->request('GET', '/');
        $token = $crawler->filter('input[name="_csrf_token"]')->attr('value');
        self::assertNotNull($token);

        return [
            'name' => 'Jane Visitor',
            'email' => self::VISITOR_EMAIL,
            'subject' => 'Test subject',
            'message' => self::VISITOR_MESSAGE,
            'recaptcha-response' => 'test-recaptcha-token',
            '_csrf_token' => $token,
        ];
    }

    private function clientWith(Response $recaptcha): KernelBrowser
    {
        $client = static::createClient();
        // Keep a single container for the whole test: KernelBrowser reboots
        // between requests, which would drop the service overrides below.
        $client->disableReboot();

        $mock = $this->createMock(ReCaptcha::class);
        $mock->method('verify')->willReturn($recaptcha);
        static::getContainer()->set(ReCaptcha::class, $mock);
        static::getContainer()->set(SesClient::class, $this->ses);
        static::getContainer()->set('logger', $this->logger);

        return $client;
    }

    private function assertLogContainsAcceptedWithMessageId(): void
    {
        $found = false;
        foreach ($this->logger->records as $record) {
            if ('contact.send' === $record['message']
                && 'accepted' === ($record['context']['outcome'] ?? null)
                && 'ses-message-id-1' === ($record['context']['ses_message_id'] ?? null)
            ) {
                $found = true;
            }
        }
        self::assertTrue($found, 'Expected an accepted contact.send log with the SES message ID.');
    }

    private function assertNoPiiOrSecretsInLogs(): void
    {
        $dump = $this->logger->dumpedRecords();

        foreach ([self::VISITOR_EMAIL, self::VISITOR_MESSAGE, 'Test subject', 'Jane Visitor', 'op-secret', 'test-secret', 'test-recaptcha-token'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $dump, "Logs must not contain: {$forbidden}");
        }
    }
}
