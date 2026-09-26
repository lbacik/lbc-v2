<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mail;

use App\Mail\ContactMessage;
use App\Mail\InvalidMailBoundaryException;
use App\Mail\MailBoundary;
use App\Mail\MailDeliveryException;
use App\Mail\SesTenantMailSender;
use App\Tests\Support\Mail\FakeSesClient;
use PHPUnit\Framework\TestCase;

class SesTenantMailSenderTest extends TestCase
{
    private const TENANT = 'lbc-contact-tenant';

    private FakeSesClient $client;

    protected function setUp(): void
    {
        $this->client = new FakeSesClient();
    }

    public function testSendSetsExactSesV2RequestFields(): void
    {
        $sender = $this->sender();
        $messageId = $sender->send($this->contactMessage());

        self::assertSame('ses-message-id-1', $messageId);

        $request = $this->client->lastRequest;
        self::assertNotNull($request);
        self::assertSame(MailBoundary::FROM_ADDRESS, $request->getFromEmailAddress());
        self::assertSame(self::TENANT, $request->getTenantName());
        self::assertSame(MailBoundary::CONFIGURATION_SET, $request->getConfigurationSetName());
        self::assertSame(['jane@example.com'], $request->getReplyToAddresses());
        self::assertSame(
            [MailBoundary::DEFAULT_RECIPIENT],
            $request->getDestination()?->getToAddresses(),
        );

        $simple = $request->getContent()?->getSimple();
        self::assertNotNull($simple);
        self::assertSame('Hello', $simple->getSubject()?->getData());
        self::assertSame('UTF-8', $simple->getSubject()?->getCharset());
        self::assertStringContainsString('Just saying hi.', (string) $simple->getBody()?->getText()?->getData());
        self::assertStringNotContainsString('no-reply@', (string) $simple->getBody()?->getText()?->getData());
    }

    public function testBoundaryIsImmutableAcrossMessages(): void
    {
        $sender = $this->sender();
        $sender->send($this->contactMessage('jane@example.com'));

        $first = $this->client->lastRequest;
        $sender->send($this->contactMessage('attacker@evil.example'));

        $second = $this->client->lastRequest;
        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertSame($first->getTenantName(), $second->getTenantName());
        self::assertSame($first->getConfigurationSetName(), $second->getConfigurationSetName());
        self::assertSame($first->getFromEmailAddress(), $second->getFromEmailAddress());
        self::assertSame($first->getDestination()?->getToAddresses(), $second->getDestination()?->getToAddresses());
        self::assertSame(['attacker@evil.example'], $second->getReplyToAddresses());
    }

    public function testSendExposesNoPerMessageBoundarySelection(): void
    {
        $parameters = (new \ReflectionMethod(SesTenantMailSender::class, 'send'))->getParameters();

        self::assertCount(1, $parameters);
        self::assertSame(ContactMessage::class, $parameters[0]->getType()->getName());
    }

    public function testMissingTenantIsRejectedWithoutCallingSes(): void
    {
        $sender = new SesTenantMailSender(
            $this->client,
            new MailBoundary('', MailBoundary::CONFIGURATION_SET, MailBoundary::REGION, MailBoundary::FROM_ADDRESS, MailBoundary::DEFAULT_RECIPIENT),
        );

        $this->expectException(InvalidMailBoundaryException::class);
        try {
            $sender->send($this->contactMessage());
        } finally {
            self::assertSame(0, $this->client->sendCalls);
        }
    }

    /**
     * @dataProvider wrongBoundaryProvider
     */
    public function testWrongBoundaryIsRejectedWithoutCallingSes(MailBoundary $boundary): void
    {
        $sender = new SesTenantMailSender($this->client, $boundary);

        try {
            $sender->send($this->contactMessage());
            self::fail('Expected InvalidMailBoundaryException');
        } catch (InvalidMailBoundaryException) {
            self::assertSame(0, $this->client->sendCalls);
        }
    }

    /**
     * @return iterable<string, array{MailBoundary}>
     */
    public function wrongBoundaryProvider(): iterable
    {
        yield 'wrong tenant with whitespace' => [new MailBoundary(
            'tenant with spaces', MailBoundary::CONFIGURATION_SET, MailBoundary::REGION, MailBoundary::FROM_ADDRESS, MailBoundary::DEFAULT_RECIPIENT,
        )];
        yield 'wrong configuration set' => [new MailBoundary(
            self::TENANT, 'my-first-configuration-set', MailBoundary::REGION, MailBoundary::FROM_ADDRESS, MailBoundary::DEFAULT_RECIPIENT,
        )];
        yield 'wrong region' => [new MailBoundary(
            self::TENANT, MailBoundary::CONFIGURATION_SET, 'us-east-1', MailBoundary::FROM_ADDRESS, MailBoundary::DEFAULT_RECIPIENT,
        )];
        yield 'wrong from' => [new MailBoundary(
            self::TENANT, MailBoundary::CONFIGURATION_SET, MailBoundary::REGION, 'attacker@evil.example', MailBoundary::DEFAULT_RECIPIENT,
        )];
        yield 'missing recipient' => [new MailBoundary(
            self::TENANT, MailBoundary::CONFIGURATION_SET, MailBoundary::REGION, MailBoundary::FROM_ADDRESS, '',
        )];
    }

    public function testSesFailureMapsToSafeException(): void
    {
        $this->client->failure = new \RuntimeException('AWS exploded: credentials xyz secret abc');

        try {
            $this->sender()->send($this->contactMessage());
            self::fail('Expected MailDeliveryException');
        } catch (MailDeliveryException $e) {
            self::assertStringNotContainsString('exploded', $e->getMessage());
            self::assertStringNotContainsString('xyz', $e->getMessage());
            self::assertStringNotContainsString('jane@example.com', $e->getMessage());
            self::assertStringNotContainsString('Just saying hi.', $e->getMessage());
        }
    }

    public function testMissingSesMessageIdIsTreatedAsFailure(): void
    {
        $this->client->messageId = '';

        $this->expectException(MailDeliveryException::class);
        $this->sender()->send($this->contactMessage());
    }

    private function sender(): SesTenantMailSender
    {
        return new SesTenantMailSender(
            $this->client,
            new MailBoundary(self::TENANT, MailBoundary::CONFIGURATION_SET, MailBoundary::REGION, MailBoundary::FROM_ADDRESS, MailBoundary::DEFAULT_RECIPIENT),
        );
    }

    private function contactMessage(string $email = 'jane@example.com'): ContactMessage
    {
        return ContactMessage::fromArray([
            'name' => 'Jane Doe',
            'email' => $email,
            'subject' => 'Hello',
            'message' => 'Just saying hi.',
        ]);
    }
}
