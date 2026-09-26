<?php

declare(strict_types=1);

namespace App\Mail;

enum MailMode: string
{
    case Disabled = 'disabled';
    case Validation = 'validation';
    case Enabled = 'enabled';
}
