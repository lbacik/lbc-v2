<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{

    #[Route('/')]
    public function index(): Response
    {
        return $this->render('index.html.twig', [
            'portfolioItems' => $this->portfolioItems(),
        ]);
    }

    #[Route('/portfolio-details/{slug}', name: "portfolio_details")]
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

    private function portfolioItems(): array
    {
        return [
            [
                'imageUrl' => 'https://placehold.co/800x1000?font=roboto&text=web (in progress...)',
                'title' => '',
                'category' => 'Web',
                'filter' => 'web',
                'slug' => null,
            ],
            [
                'imageUrl' => '/assets/img/portfolio-2/phparch-1.png',
                'title' => 'PHP Architect',
                'category' => 'Publication',
                'filter' => 'pub',
                'slug' => 'phparch',
            ],
            [
                'imageUrl' => '/assets/img/portfolio-2/vo-1.png',
                'title' => 'Value Object',
                'category' => 'Library',
                'filter' => 'lib',
                'slug' => 'value-object',
            ],
            [
                'imageUrl' => '/assets/img/portfolio-2/progmag-1.png',
                'title' => 'Programista',
                'category' => 'Publication',
                'filter' => 'pub',
                'slug' => 'progmag',
            ],
            [
                'imageUrl' => '/assets/img/portfolio-2/medium-2.png',
                'title' => 'Medium',
                'category' => 'Publication',
                'filter' => 'pub',
                'slug' => 'medium',
            ],
        ];
    }

    private function portfolioDetailsData(string $slug): array
    {
        return match($slug) {
            'phparch' => [
                'PHP',
                'https://www.phparch.com/article/value-objects/',
                'phparch.com',
                'PHP Architect',
                'PHP Architect is a magazine dedicated to PHP developers. I have been a subscriber for many years and have written a few articles for them.',
                [
                    '/assets/img/portfolio-2/phparch-1.png',
                    '/assets/img/portfolio-2/phparch-2.png',
                    '/assets/img/portfolio-2/phparch-3.png',
                ],
            ],
            'value-object' => [
                'Library',
                'https://packagist.org/packages/lbacik/value-object',
                'value object',
                'Value Object',
                'foo bar',
                [
                    '/assets/img/portfolio-2/vo-1.png',
                    '/assets/img/portfolio-2/vo-2.png',
                ],
            ],
            'medium' => [
                'PHP/Python/DevOps',
                'https://lbacik.medium.com/',
                'lbacik.medium.com',
                'Medium',
                'foo bar',
                [
                    '/assets/img/portfolio-2/medium-1.png',
                    '/assets/img/portfolio-2/medium-2.png',
                    '/assets/img/portfolio-2/medium-3.png',
                    '/assets/img/portfolio-2/medium-4.png',
                    '/assets/img/portfolio-2/medium-5.png',
                ],
            ],
            'progmag' => [
                'Publication',
                'https://programistamag.pl/networking-eksperymenty-z-siecia-warstwa-druga-i-protokol-arp/',
                'article summary',
                'foo',
                'bar',
                [
                    '/assets/img/portfolio-2/progmag-1.png',
                    '/assets/img/portfolio-2/progmag-2.png',
                    '/assets/img/portfolio-2/progmag-3.png',
                ],
            ],
            default => throw new NotFoundHttpException(),
        };
    }
}
