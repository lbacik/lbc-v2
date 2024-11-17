<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class PortfolioService
{
    private const PORTFOLIO = __DIR__ . '/../../config/portfolio.yaml';
    private const PORTFOLIO_IMAGE_PATH = '/template/img/portfolio-2/';

    private array|null $portfolioItems = null;

    public function __construct(private LoggerInterface $logger)
    {
    }

    public function getItems(): array
    {
        $this->readFile();

        $result = array_map(
            fn (string $itemName) => $this->mapItemToGeneral($itemName, $this->portfolioItems[$itemName]),
            array_keys($this->portfolioItems)
        );

        return $result;
    }

    public function getDetails(string $slug): array
    {
        $this->readFile();

        $item = array_filter(
            $this->portfolioItems,
            fn (array $item) => $item['slug'] === $slug,
            ARRAY_FILTER_USE_BOTH
        );

        return $this->mapItemToDetails($item);
    }

    private function readFile(): void
    {
        if ($this->portfolioItems !== null) {
            return;
        }

        try {
            $this->portfolioItems = Yaml::parseFile(self::PORTFOLIO);
        } catch (ParseException $exception) {
            $this->logger->error($exception->getMessage(), [
                'exception' => $exception,
            ]);
        }
    }

    private function mapItemToGeneral(string $itemName, array $item): array
    {
        return [
            'imageUrl' => self::PORTFOLIO_IMAGE_PATH . $item['image'],
            'title' => $itemName,
            'category' => $item['category'],
            'filter' => $item['filter'],
            'slug' => $item['slug'],
        ];
    }

    private function mapItemToDetails(array $item): array
    {
        $itemName = array_key_first($item);
        $itemDetails = $item[$itemName];

        return [
            $itemDetails['type'],
            $itemDetails['url']['href'],
            $itemDetails['url']['name'],
            $itemName,
            $itemDetails['description'],
            array_map(
                fn (string $image) => self::PORTFOLIO_IMAGE_PATH . $image,
                $itemDetails['carousel'],
            ),
        ];
    }
}
