<?php

declare(strict_types=1);

namespace App\Mail;

use AsyncAws\Ses\Input\SendEmailRequest;
use AsyncAws\Ses\SesClient;
use AsyncAws\Ses\ValueObject\Destination;
use AsyncAws\Ses\ValueObject\EmailContent;
use AsyncAws\Ses\ValueObject\Message;
use AsyncAws\Ses\ValueObject\Content;
use AsyncAws\Ses\ValueObject\Body;

/**
 * Deployment-owned adapter around the SES v2 SendEmail API.
 *
 * Tenant, configuration set, region, From identity, and recipient always come
 * from the deployment-owned MailBoundary. The send() signature takes only a
 * ContactMessage, so request input can never override the boundary.
 */
final class SesTenantMailSender
{
    public function __construct(
        private readonly SesClient $client,
        private readonly MailBoundary $boundary,
    ) {
    }

    /**
     * Sends the message and returns the SES message ID.
     *
     * @throws InvalidMailBoundaryException when the deployment boundary is missing or wrong
     * @throws MailDeliveryException        when SES does not accept the message (safe message, no PII)
     */
    public function send(ContactMessage $message): string
    {
        $this->boundary->assertValid();

        $request = new SendEmailRequest([
            'FromEmailAddress' => $this->boundary->fromAddress,
            'Destination' => new Destination(['ToAddresses' => [$this->boundary->operatorRecipient]]),
            'ReplyToAddresses' => [$message->email],
            'ConfigurationSetName' => $this->boundary->configurationSetName,
            'TenantName' => $this->boundary->tenantName,
            'Content' => new EmailContent([
                'Simple' => new Message([
                    'Subject' => new Content(['Data' => $message->subject, 'Charset' => 'UTF-8']),
                    'Body' => new Body([
                        'Text' => new Content(['Data' => $this->body($message), 'Charset' => 'UTF-8']),
                    ]),
                ]),
            ]),
        ]);

        try {
            $messageId = $this->client->sendEmail($request)->getMessageId();
        } catch (\Throwable $exception) {
            throw new MailDeliveryException('The message could not be accepted for delivery. Please try again later.', 0, $exception);
        }

        if (null === $messageId || '' === $messageId) {
            throw MailDeliveryException::sendFailed();
        }

        return $messageId;
    }

    private function body(ContactMessage $message): string
    {
        return \sprintf("Name: %s\n\n%s", $message->name, $message->message);
    }
}
