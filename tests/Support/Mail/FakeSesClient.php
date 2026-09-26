<?php

declare(strict_types=1);

namespace App\Tests\Support\Mail;

use AsyncAws\Ses\Input\SendEmailRequest;
use AsyncAws\Ses\Result\SendEmailResponse;
use AsyncAws\Ses\SesClient;

final class StubSendEmailResponse extends SendEmailResponse
{
    public function __construct(private readonly string $id)
    {
    }

    public function getMessageId(): ?string
    {
        return $this->id;
    }
}

final class FakeSesClient extends SesClient
{
    public ?SendEmailRequest $lastRequest = null;
    public int $sendCalls = 0;
    public string $messageId = 'ses-message-id-1';
    public ?\Throwable $failure = null;

    public function __construct()
    {
    }

    public function sendEmail($input): SendEmailResponse
    {
        ++$this->sendCalls;
        $this->lastRequest = SendEmailRequest::create($input);

        if (null !== $this->failure) {
            throw $this->failure;
        }

        return new StubSendEmailResponse($this->messageId);
    }
}
