<?php

namespace EduLazaro\Larasources\Origins;

use EduLazaro\Larasources\Exceptions\OriginException;

abstract class ScraperOrigin extends Origin
{
    public function fetch(array $arguments = []): array
    {
        $url = $this->url($arguments);

        if (!$url) {
            throw new OriginException('URL is required for scraping');
        }

        $html = $this->getHtml($url);

        if (!$html) {
            throw new OriginException("Failed to fetch HTML from: {$url}");
        }

        $data = $this->extract($html);

        return $this->transformResponse($data);
    }

    abstract protected function url(array $arguments): string;

    abstract protected function selectors(): array;

    protected function getHtml(string $url): ?string
    {
        if (class_exists(\EduLazaro\Larascraper\Scraper::class)) {
            $scraper = new \EduLazaro\Larascraper\Scraper();
            return $scraper->get($url);
        }

        $response = $this->http()->get($url);

        return $response->successful() ? $response->body() : null;
    }

    protected function extract(string $html): array
    {
        if (class_exists(\EduLazaro\Larascraper\Scraper::class)) {
            return $this->extractWithLarascraper($html);
        }

        return $this->extractWithDom($html);
    }

    protected function extractWithLarascraper(string $html): array
    {
        $dom = new \EduLazaro\Larascraper\Dom($html);
        $data = [];

        foreach ($this->selectors() as $field => $selector) {
            $data[$field] = $dom->find($selector)?->text();
        }

        return $data;
    }

    protected function extractWithDom(string $html): array
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($html, LIBXML_NOERROR);
        $xpath = new \DOMXPath($dom);
        $data = [];

        foreach ($this->selectors() as $field => $selector) {
            $cssSelector = $this->cssToXpath($selector);
            $nodes = $xpath->query($cssSelector);
            $data[$field] = $nodes && $nodes->length > 0 ? trim($nodes->item(0)->textContent) : null;
        }

        return $data;
    }

    protected function cssToXpath(string $css): string
    {
        $xpath = './/' . preg_replace_callback('/([a-zA-Z0-9\-]+)?(?:#([a-zA-Z0-9\-_]+))?(?:\.([a-zA-Z0-9\-_]+))?/', function ($m) {
            $tag = $m[1] ?: '*';
            $expr = $tag;
            if (!empty($m[2])) {
                $expr .= "[@id='{$m[2]}']";
            }
            if (!empty($m[3])) {
                $expr .= "[contains(concat(' ',normalize-space(@class),' '),' {$m[3]} ')]";
            }
            return $expr;
        }, $css);

        return str_replace(' ', '//', $xpath);
    }

    protected function transformResponse(array $data): array
    {
        return $data;
    }
}
