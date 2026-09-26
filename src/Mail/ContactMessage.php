<?php

declare(strict_types=1);

namespace App\Mail;

use Symfony\Component\Validator\Constraints as Assert;

final class ContactMessage
{
    public const MAX_NAME_BYTES = 100;
    public const MAX_EMAIL_BYTES = 254;
    public const MAX_SUBJECT_BYTES = 200;
    public const MAX_MESSAGE_BYTES = 4000;

    private const ALLOWED_FIELDS = ['name', 'email', 'subject', 'message'];

    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: self::MAX_NAME_BYTES)]
        public readonly string $name,
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Email]
        #[Assert\Length(max: self::MAX_EMAIL_BYTES)]
        public readonly string $email,
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: self::MAX_SUBJECT_BYTES)]
        public readonly string $subject,
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: self::MAX_MESSAGE_BYTES)]
        public readonly string $message,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        foreach (array_keys($data) as $field) {
            if (!\in_array($field, self::ALLOWED_FIELDS, true)) {
                throw new ContactValidationException(\sprintf('Unexpected contact field "%s".', $field));
            }
        }

        foreach (self::ALLOWED_FIELDS as $field) {
            if (!isset($data[$field]) || !\is_string($data[$field])) {
                throw new ContactValidationException(\sprintf('Missing contact field "%s".', $field));
            }
        }

        $name = self::normalize($data['name']);
        $email = self::normalize($data['email']);
        $subject = self::normalize($data['subject']);
        $message = self::normalize($data['message']);

        self::assertByteLimit('name', $name, self::MAX_NAME_BYTES);
        self::assertByteLimit('email', $email, self::MAX_EMAIL_BYTES);
        self::assertByteLimit('subject', $subject, self::MAX_SUBJECT_BYTES);
        self::assertByteLimit('message', $message, self::MAX_MESSAGE_BYTES);

        return new self($name, $email, $subject, $message);
    }

    private static function normalize(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        // Strip control characters except horizontal tab and line feed.
        $value = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);

        return trim($value);
    }

    private static function assertByteLimit(string $field, string $value, int $maxBytes): void
    {
        if (\strlen($value) > $maxBytes) {
            throw new ContactValidationException(\sprintf('Contact field "%s" exceeds %d bytes.', $field, $maxBytes));
        }
    }
}
