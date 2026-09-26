<?php

declare(strict_types=1);

namespace App\Mail;

final class MailNotAllowedException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $reason,
    ) {
        parent::__construct($message);
    }

    public function getReason(): string
    {
        return $this->reason;
    }
}
