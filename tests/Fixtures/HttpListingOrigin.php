<?php

namespace EduLazaro\Larasources\Tests\Fixtures;

use EduLazaro\Larasources\Origins\RemoteOrigin;

class HttpListingOrigin extends RemoteOrigin
{
    protected string $baseUrl = 'https://listings.test';

    protected string $authType = 'bearer';

    public static function getAlias(): string
    {
        return 'http_listing';
    }

    protected function endpoint(array $arguments): string
    {
        return '/listings/' . ($arguments['id'] ?? '');
    }

    protected function storeEndpoint(array $data): string
    {
        return '/listings';
    }
}
