<?php

declare(strict_types=1);

namespace App\Service;

use Exception;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class SendEmailService
{
    public const TOKEN = '109455adfauuiidf3343434';

    public function __construct(
        private MailerInterface $mailer,
    ) {
    }

    public function send(array $input): void
    {
        if (!isset($input['token']) || $input['token'] !== self::TOKEN) {
            throw new Exception('contact - token not valid');
        }

        $name = $input['name'];
        $email = $input['email'];
        $subject = $input['subject'];
        $message = $input['message'];
        $to = 'lukasz@lukaszbacik.com';

        $this->mailer->send(
            (new Email())
                ->from('contact-lbc@gprodb.com')
                ->to($to)
                ->subject($subject)
                ->text("name: {$name}\nemail: {$email}\n\n{$message}")
        );
    }
}
