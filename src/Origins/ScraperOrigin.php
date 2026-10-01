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

        // Without a charset declaration libxml reads the page as ISO-8859-1 and
        // every accent comes out mangled. Numeric entities survive that.
        @$dom->loadHTML(
            mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8'),
            LIBXML_NOERROR
        );
        $xpath = new \DOMXPath($dom);
        $data = [];

        foreach ($this->selectors() as $field => $selector) {
            $cssSelector = $this->cssToXpath($selector);
            $nodes = $xpath->query($cssSelector);
            $data[$field] = $nodes && $nodes->length > 0 ? trim($nodes->item(0)->textContent) : null;
        }

        return $data;
    }

    /**
     * Translate a simple CSS selector into XPath.
     *
     * Tags, ids, classes and descendants, which is what `selectors()` is for.
     * Anything beyond that wants a real CSS selector library, and larascraper
     * is used instead when it is installed.
     */
    protected function cssToXpath(string $css): string
    {
        $steps = preg_split('/\s+/', trim($css), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $path = '';

        foreach ($steps as $step) {
            $path .= '//' . $this->cssStepToXpath($step);
        }

        return '.' . ($path ?: '//*');
    }

    protected function cssStepToXpath(string $step): string
    {
        preg_match('/^([a-zA-Z0-9\-]+)?(#[a-zA-Z0-9\-_]+)?((?:\.[a-zA-Z0-9\-_]+)*)$/', $step, $matches);

        $tag = $matches[1] ?? '';

        $expression = $tag !== '' ? $tag : '*';

        if (!empty($matches[2])) {
            $expression .= "[@id='" . ltrim($matches[2], '#') . "']";
        }

        foreach (array_filter(explode('.', $matches[3] ?? '')) as $class) {
            $expression .= "[contains(concat(' ',normalize-space(@class),' '),' {$class} ')]";
        }

        return $expression;
    }

    protected function transformResponse(array $data): array
    {
        return $data;
    }
}
