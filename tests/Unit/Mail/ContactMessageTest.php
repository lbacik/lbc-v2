<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mail;

use App\Mail\ContactMessage;
use App\Mail\ContactValidationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ContactMessageTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testValidPayloadPassesValidation(): void
    {
        $message = ContactMessage::fromArray([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'subject' => 'Hello',
            'message' => 'Just saying hi.',
        ]);

        self::assertSame([], $this->violations($message));
        self::assertSame('jane@example.com', $message->email);
    }

    public function testFromArrayNormalizesText(): void
    {
        $message = ContactMessage::fromArray([
            'name' => "  Jane Doe \t",
            'email' => '  JANE@example.com  ',
            'subject' => "Hi\r\nthere",
            'message' => "line one\r\nline two\x00 with control\x07chars",
        ]);

        self::assertSame('Jane Doe', $message->name);
        self::assertSame('JANE@example.com', $message->email);
        self::assertSame("Hi\nthere", $message->subject);
        self::assertSame("line one\nline two with controlchars", $message->message);
    }

    public function testUnknownFieldIsRejected(): void
    {
        $this->expectException(ContactValidationException::class);

        ContactMessage::fromArray([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'subject' => 'Hi',
            'message' => 'Hello',
            'token' => '109455adfauuiidf3343434',
        ]);
    }

    public function testBoundaryOverrideFieldsAreRejected(): void
    {
        $rejected = 0;
        foreach (['tenant', 'tenantName', 'configurationSet', 'from', 'region', 'to'] as $field) {
            try {
                ContactMessage::fromArray([
                    'name' => 'Jane',
                    'email' => 'jane@example.com',
                    'subject' => 'Hi',
                    'message' => 'Hello',
                    $field => 'attacker-value',
                ]);
                self::fail("Field '{$field}' was accepted");
            } catch (ContactValidationException) {
                ++$rejected;
            }
        }

        self::assertSame(6, $rejected);
    }

    public function testMissingFieldIsRejected(): void
    {
        $this->expectException(ContactValidationException::class);

        ContactMessage::fromArray([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'subject' => 'Hi',
        ]);
    }

    public function testOverlongFieldsAreRejectedByByteLimit(): void
    {
        $this->expectException(ContactValidationException::class);

        ContactMessage::fromArray([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'subject' => 'Hi',
            'message' => str_repeat('a', ContactMessage::MAX_MESSAGE_BYTES + 1),
        ]);
    }

    public function testBlankNameFailsValidation(): void
    {
        $message = ContactMessage::fromArray([
            'name' => '   ',
            'email' => 'jane@example.com',
            'subject' => 'Hi',
            'message' => 'Hello',
        ]);

        self::assertNotSame([], $this->violations($message));
    }

    public function testInvalidVisitorEmailFailsValidation(): void
    {
        $message = ContactMessage::fromArray([
            'name' => 'Jane',
            'email' => 'not-an-email',
            'subject' => 'Hi',
            'message' => 'Hello',
        ]);

        $violations = $this->violations($message);
        self::assertNotSame([], $violations);
        self::assertStringContainsString('email', $violations[0]);
    }

    /**
     * @return list<string>
     */
    private function violations(ContactMessage $message): array
    {
        $result = [];
        foreach ($this->validator->validate($message) as $violation) {
            $result[] = $violation->getPropertyPath().': '.$violation->getMessage();
        }

        return $result;
    }
}
