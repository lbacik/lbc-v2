<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\SendEmailService;
use Psr\Log\LoggerInterface;
use ReCaptcha\ReCaptcha;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

class HomeController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private SendEmailService $sendEmailService,
        private readonly ReCaptcha $reCaptcha,
    ) {
    }

    #[Route('/', name: 'home', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        return $this->render('index.html.twig', [
            'portfolioItems' => $this->portfolioItems(),
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
        ] = $this->portfolioDetailsData($slug);

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

    private function portfolioItems(): array
    {
        return [
            [
                'imageUrl' => '/template/img/portfolio-2/fortune-01.jpeg',
                'title' => 'Fortune',
                'category' => 'Web',
                'filter' => 'web',
                'slug' => 'fortune',
            ],
            [
                'imageUrl' => '/template/img/portfolio-2/phparch-c1.jpeg',
                'title' => 'PHP Architect',
                'category' => 'Publication',
                'filter' => 'pub',
                'slug' => 'phparch',
            ],
            [
                'imageUrl' => '/template/img/portfolio-2/vo-1.jpeg',
                'title' => 'Value Object',
                'category' => 'Library',
                'filter' => 'lib',
                'slug' => 'value-object',
            ],
            [
                'imageUrl' => '/template/img/portfolio-2/progmag-c1.jpeg',
                'title' => 'Programista',
                'category' => 'Publication',
                'filter' => 'pub',
                'slug' => 'progmag',
            ],
            [
                'imageUrl' => '/template/img/portfolio-2/medium-2.jpg',
                'title' => 'Medium',
                'category' => 'Publication',
                'filter' => 'pub',
                'slug' => 'medium',
            ],
            [
                'imageUrl' => '/template/img/portfolio-2/jsonhub-1.jpeg',
                'title' => 'JsonHub',
                'category' => 'Web',
                'filter' => 'web',
                'slug' => 'jsonhub',
            ],
            [
                'imageUrl' => '/template/img/portfolio-2/gprodb-01.jpeg',
                'title' => 'GProDB',
                'category' => 'Web',
                'filter' => 'web',
                'slug' => 'gprodb',
            ],
            [
                'imageUrl' => '/template/img/portfolio-2/glife-01.jpeg',
                'title' => 'GLife',
                'category' => 'Web',
                'filter' => 'web',
                'slug' => 'glife',
            ],
        ];
    }

    private function portfolioDetailsData(string $slug): array
    {
        return match($slug) {
            'phparch' => [
                'Publication',
                'https://www.phparch.com/article/value-objects/',
                'phparch.com',
                'PHP Architect',
                'I was thrilled when I received a message from PHP Architect, one of the most renowned PHP magazines 
                in the world, expressing their interest in publishing my article. The article, titled "Value Objects," 
                has been featured in the June 2023 issue of the PHP Architect magazine!',
                [
                    '/assets/img/portfolio-2/phparch-1.jpeg',
                    '/assets/img/portfolio-2/phparch-l2.jpeg',
                    '/assets/img/portfolio-2/phparch-l3.jpeg',
                ],
            ],
            'value-object' => [
                'Library',
                'https://packagist.org/packages/lbacik/value-object',
                'value object',
                'Value Object',
                'The experiment resulted in a little library I still use today!'
                    . ' It is available on <a href="https://packagist.org/packages/lbacik/value-object" target="_blank">Packagist</a> / <a href="https://github.com/lbacik/value-object" target="_blank">GitHub</a>'
                    . ' and you can read more about it on <a href="https://lbacik.medium.com/value-objects-2db63b9ef5e9" target="_blank">Medium</a>.'
                    . ' The library is also covered in the <a href="https://www.phparch.com/article/value-objects/" target="_blank">PHP Architect article</a>.',
                [
                    '/assets/img/portfolio-2/vo-1.jpeg',
                    '/assets/img/portfolio-2/vo-2.jpeg',
                ],
            ],
            'medium' => [
                'Publication',
                'https://lbacik.medium.com/',
                'lbacik.medium.com',
                'Medium',
                'I sometimes write on Medium about various topics. Check out my articles on Linux Namespaces, containers, PHP, and applications I have written (not only in PHP).',
                [
                    '/assets/img/portfolio-2/medium-2.jpg',
                    '/assets/img/portfolio-2/medium-3.jpeg',
                    '/assets/img/portfolio-2/medium-5.jpeg',
                ],
            ],
            'progmag' => [
                'Publication',
                'https://programistamag.pl/networking-eksperymenty-z-siecia-warstwa-druga-i-protokol-arp/',
                'programistamag.pl',
                'Programista',
                'Article about networking and some "mysteries" in the ARP protocol, along with a small exercise on 
                recompiling the Linux kernel to address them, was published in the July 2019 issue of Programista 
                magazine (Polish language). The magazine is a well-known publication in Poland that covers programming 
                and the IT industry.',
                [
                    '/assets/img/portfolio-2/progmag-l1.jpeg',
                    '/assets/img/portfolio-2/progmag-l2.jpeg',
                    '/assets/img/portfolio-2/progmag-l3.jpeg',
                ],
            ],
            'fortune' => [
                'Application',
                'https://fortune.luka.sh',
                'fortune.luka.sh',
                'Fortune',
                '<strong>Stack</strong>: React, Redux, Bootstrap, Python, FastAPI, ElasticSearch, Docker<br/><br/>
                Web page that allows you to play with fortune cookies in the browser. The API is written in Python and utilises the 
                <a href="https://pypi.org/project/lfortune/" target="_blank">lfortune</a> library (another one of my projects), which also has a CLI interface
                <br/><br /> More info about the project can be found on the project\'s <a href="https://fortune.luka.sh/#about" target="_blank">About</a> page!
                ',
                [
                    '/assets/img/portfolio-2/fortune-01.jpeg',
                    '/assets/img/portfolio-2/fortune-02.jpeg',
                    '/assets/img/portfolio-2/fortune-03.jpeg',
                ],
            ],
            'jsonhub' => [
                'Application',
                'https://jsonhub.cloud',
                'jsonhub.cloud',
                'JsonHub',
                '<strong>Stack</strong>: PHP (Symfony), Stimulus/Turbo, Api Platform (REST API), Bootstrap, MySQL, Redis, Docker<br/><br/>
                Generally, JsonHub is a web application that allows you to store and share JSON and 
                <a href="https://json-schema.org" target="_blank">JSON Schema</a> documents. But - it is only the tip of the 
                iceberg - it is full of potential! Currently (01.2024), the project is in its early development phase. 
                Although the design needs improvement, it is already functional. 
                ',
                [
                    '/assets/img/portfolio-2/jsonhub-1.jpeg',
                    '/assets/img/portfolio-2/jsonhub-2.jpeg',
                    '/assets/img/portfolio-2/jsonhub-3.jpeg',
                    '/assets/img/portfolio-2/jsonhub-4.jpeg',
                ],
            ],
            'gprodb' => [
                'Application',
                'https://pages.gprodb.com/018c0c05-be94-7b11-aaa5-9d59c24cbbda',
                'pages.gprodb.com',
                'GProDB',
                '<strong>Stack</strong>: PHP (Symfony), Bootstrap, Docker<br/><br/>
                 This straightforward web application allows you to quickly create your business\'s web page 
                 (so-called landing page). It utilised the <a href="/portfolio-details/jsonhub">JsonHub project</a> 
                 to store the data. More friendly UI (than the native JsonHub one) and a dedicated searching mechanism 
                 are the main future plans!  
                ',
                [
                    '/assets/img/portfolio-2/gprodb-01.jpeg',
                    '/assets/img/portfolio-2/gprodb-02.jpeg',
                    '/assets/img/portfolio-2/gprodb-03.jpeg',
                    '/assets/img/portfolio-2/gprodb-04.jpeg',
                ],
            ],
            'glife' => [
                'Application',
                'https://glife.luka.sh',
                'glife.luka.sh',
                'GLife',
                '<strong>Stack</strong>: PHP (Symfony), AssetMapper, Stimulus, Turbo, Tailwindcss, JSON Schema, Python, RabbitMQ, Docker<br/><br/>
                GLife is a web application that allows you to play Conway\'s Game of Life. It utilises the Genetic 
                Algorithm to determine the best starting state for the game based on the Fitness Function configuration. 
                This portion of the project was coded in Python, with RabbitMQ used for communication.
                ',
                [
                    '/assets/img/portfolio-2/glife-01.jpeg',
                    '/assets/img/portfolio-2/glife-02.jpeg',
                    '/assets/img/portfolio-2/glife-03.jpeg',
                ],
            ],
            default => throw new NotFoundHttpException(),
        };
    }

    private function reCaptchaSuccess(Request $request): bool
    {
        $recaptchaResponse = $request->request->get('recaptcha-response');
        $recaptcha = $this->reCaptcha->verify($recaptchaResponse, $request->getClientIp());

        return $recaptcha->isSuccess();
    }
}
