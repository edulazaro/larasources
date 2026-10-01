<?php

namespace EduLazaro\Larasources\Tests\Fixtures;

use EduLazaro\Larasources\Origins\ScraperOrigin;

class HtmlRecipeOrigin extends ScraperOrigin
{
    public static function getAlias(): string
    {
        return 'html_recipe';
    }

    protected function url(array $arguments): string
    {
        return $arguments['url'] ?? '';
    }

    protected function selectors(): array
    {
        return [
            'title' => 'h1',
            'author' => '.author',
            'yield' => 'div.card.servings',
            'source' => '#origin span',
        ];
    }
}
