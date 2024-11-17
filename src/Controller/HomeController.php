<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PortfolioService;
use App\Service\SendEmailService;
use Psr\Log\LoggerInterface;
use ReCaptcha\ReCaptcha;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

class HomeController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly SendEmailService $sendEmailService,
        private readonly ReCaptcha $reCaptcha,
        private readonly PortfolioService $portfolioService,
    ) {
    }

    #[Route('/', name: 'home', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        return $this->render('index.html.twig', [
            'portfolioItems' => $this->portfolioService->getItems(),
            'token' => SendEmailService::TOKEN,
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

    #[Route('/contact', name: 'contact', methods: 'POST')]
    public function contactFormData(Request $request): Response
    {
        if (!$this->reCaptchaSuccess($request)) {
            return new Response('Invalid reCAPTCHA response.');
        }

        try {
            $this->sendEmailService->send($request->request->all());

            return new Response('OK');

        } catch (Throwable $exception) {
            $this->logger->error($exception->getMessage(), [
                'exception' => $exception,
                'request' => $request->request->all(),
            ]);

            return new Response('Something went wrong. Please try again later.');
        }
    }

    private function reCaptchaSuccess(Request $request): bool
    {
        $recaptchaResponse = $request->request->get('recaptcha-response');
        $recaptcha = $this->reCaptcha->verify($recaptchaResponse, $request->getClientIp());

        return $recaptcha->isSuccess();
    }
}
