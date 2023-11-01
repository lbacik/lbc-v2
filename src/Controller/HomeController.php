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
                'imageUrl' => 'https://placehold.co/400x200?font=roboto&text=web (in progress...)',
                'title' => '',
                'category' => 'Web',
                'filter' => 'web',
                'slug' => null,
            ],
            [
                'imageUrl' => '/assets/img/portfolio-2/phparch-c1.png',
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
                'imageUrl' => '/assets/img/portfolio-2/progmag-c1.png',
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
                'Publication',
                'https://www.phparch.com/article/value-objects/',
                'phparch.com',
                'PHP Architect',
                'I was thrilled when I received a message from PHP Architect, one of the most renowned PHP magazines 
                in the world, expressing their interest in publishing my article. The article, titled "Value Objects," 
                has been featured in the June 2023 issue of the PHP Architect magazine, so be sure to check it out!',
                [
                    '/assets/img/portfolio-2/phparch-s1.png',
//                    '/assets/img/portfolio-2/phparch-2.png',
//                    '/assets/img/portfolio-2/phparch-3.png',
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
                    '/assets/img/portfolio-2/vo-1.png',
                    '/assets/img/portfolio-2/vo-2.png',
                ],
            ],
            'medium' => [
                'Publication',
                'https://lbacik.medium.com/',
                'lbacik.medium.com',
                'Medium',
                'I sometimes write on Medium about various topics. Check out my articles on Linux Namespaces, containers, PHP, and applications I have written (not only in PHP).',
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
                'programistamag.pl',
                'Programista',
                'My article about networking and some "mysteries" in the ARP protocol, along with a small exercise on 
                recompiling the Linux kernel to address them, was published in the July 2019 issue of Programista 
                magazine (Polish language). The magazine is a well-known publication in Poland that covers programming 
                and the IT industry.',
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
