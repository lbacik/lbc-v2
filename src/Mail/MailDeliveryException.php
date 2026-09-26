<?php

declare(strict_types=1);

namespace App\Mail;

final class MailDeliveryException extends \RuntimeException
{
    public static function sendFailed(): self
    {
        return new self('The message could not be accepted for delivery. Please try again later.');
    }
}
