<?php

declare(strict_types=1);

namespace App\Controller;

use App\Mail\ContactMessage;
use App\Mail\ContactRateLimiter;
use App\Mail\ContactValidationException;
use App\Mail\InvalidMailBoundaryException;
use App\Mail\MailDeliveryException;
use App\Mail\MailModeGate;
use App\Mail\MailNotAllowedException;
use App\Mail\RecaptchaVerifier;
use App\Mail\SesTenantMailSender;
use App\Service\PortfolioService;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[WithMonologChannel('contact')]
class HomeController extends AbstractController
{
    private const CSRF_TOKEN_ID = 'contact';
    private const VALIDATION_TOKEN_HEADER = 'X-Contact-Validation-Token';
    private const ALTERNATIVE_CONTACT = 'lukasz@lukaszbacik.com';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly SesTenantMailSender $mailSender,
        private readonly MailModeGate $mailModeGate,
        private readonly RecaptchaVerifier $recaptchaVerifier,
        private readonly ContactRateLimiter $rateLimiter,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly ValidatorInterface $validator,
        private readonly PortfolioService $portfolioService,
    ) {
    }

    #[Route('/', name: 'home', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('index.html.twig', [
            'portfolioItems' => $this->portfolioService->getItems(),
        ]);
    }

    #[Route('/portfolio-details/{slug}', name: 'portfolio_details')]
    public function portfolioDetails(string $slug): Response
    {
        [
            $category,
            $url,
            $urlLabel,
            $title,
            $description,
            $images
        ] = $this->portfolioService->getDetails($slug);

        return $this->render('portfolio-details.html.twig', [
            'category' => $category,
            'url' => $url,
            'urlLabel' => $urlLabel,
            'title' => $title,
            'description' => $description,
            'images' => $images,
            'language' => null,
        ]);
    }

    #[Route('/contact', name: 'contact', methods: ['POST'])]
    public function contactFormData(Request $request): Response
    {
        $correlationId = bin2hex(random_bytes(16));
        $startedAt = hrtime(true);
        $clientIp = $request->getClientIp() ?? 'unknown';

        if (!$this->rateLimiter->isAllowed($clientIp)) {
            $this->logContact($correlationId, 'rejected', 'rate-limited', null, $startedAt);

            return $this->failure('Too many requests. Please try again later.', Response::HTTP_TOO_MANY_REQUESTS);
        }

        $csrfToken = $request->request->get('_csrf_token');
        if (!\is_string($csrfToken) || !$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $csrfToken))) {
            $this->logContact($correlationId, 'rejected', 'csrf-invalid', null, $startedAt);

            return $this->failure('Invalid request. Please reload the page and try again.', Response::HTTP_FORBIDDEN);
        }

        try {
            $this->mailModeGate->assertSendAllowed($request->headers->get(self::VALIDATION_TOKEN_HEADER));
        } catch (MailNotAllowedException $exception) {
            $this->logContact($correlationId, 'rejected', 'mail-'.$exception->getReason(), null, $startedAt);
            $status = 'forbidden' === $exception->getReason() ? Response::HTTP_FORBIDDEN : Response::HTTP_SERVICE_UNAVAILABLE;

            return $this->failure('The contact form is currently unavailable.', $status);
        }

        $payload = $request->request->all();
        unset($payload['_csrf_token'], $payload['recaptcha-response']);

        try {
            $message = ContactMessage::fromArray($payload);
        } catch (ContactValidationException) {
            $this->logContact($correlationId, 'rejected', 'invalid-payload', null, $startedAt);

            return $this->failure('Please check the form and try again.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (\count($this->validator->validate($message)) > 0) {
            $this->logContact($correlationId, 'rejected', 'invalid-payload', null, $startedAt);

            return $this->failure('Please check the form and try again.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $recaptchaResponse = $request->request->get('recaptcha-response');
        $recaptcha = $this->recaptchaVerifier->verify(
            \is_string($recaptchaResponse) ? $recaptchaResponse : null,
            $clientIp,
        );
        if (!$recaptcha->ok) {
            $this->logContact($correlationId, 'rejected', 'recaptcha-'.$recaptcha->reason, null, $startedAt);

            return $this->failure('Captcha verification failed. Please try again.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $messageId = $this->mailSender->send($message);
        } catch (InvalidMailBoundaryException) {
            $this->logContact($correlationId, 'failed', 'boundary-misconfigured', null, $startedAt);

            return $this->failure('The contact form is currently unavailable.', Response::HTTP_SERVICE_UNAVAILABLE);
        } catch (MailDeliveryException) {
            $this->logContact($correlationId, 'failed', 'delivery-failed', null, $startedAt);

            return $this->failure('Something went wrong. Please try again later.', Response::HTTP_BAD_GATEWAY);
        }

        $this->logContact($correlationId, 'accepted', 'accepted-for-delivery-attempt', $messageId, $startedAt);

        // 202 with the legacy 'OK' body: success means only accepted for a
        // delivery attempt after SES returned a message ID. It never claims
        // delivery or read confirmation. The body keeps the existing
        // php-email-form frontend working (it checks data.trim() == 'OK').
        return new Response('OK', Response::HTTP_ACCEPTED);
    }

    private function failure(string $message, int $status): Response
    {
        return new Response(
            \sprintf('%s You can also write directly to %s.', $message, self::ALTERNATIVE_CONTACT),
            $status,
        );
    }

    private function logContact(
        string $correlationId,
        string $outcome,
        string $reason,
        ?string $sesMessageId,
        int $startedAt,
    ): void {
        // Safe fields only: correlation ID, outcome category, SES message ID,
        // latency, and mail mode. Never log content, addresses, tokens,
        // credentials, or raw AWS exceptions.
        $this->logger->info('contact.send', [
            'correlation_id' => $correlationId,
            'outcome' => $outcome,
            'reason' => $reason,
            'ses_message_id' => $sesMessageId,
            'mail_mode' => $this->mailModeGate->mode()->value,
            'audit' => $this->mailModeGate->requiresAudit(),
            'duration_ms' => (int) ((hrtime(true) - $startedAt) / 1e6),
        ]);
    }
}
