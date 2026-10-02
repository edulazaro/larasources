<?php

namespace EduLazaro\Larasources\Tests\Fixtures;

use EduLazaro\Larasources\Origins\Origin;

/**
 * Origin that goes through the package's HTTP client, so the timeout and retry
 * resolution can be exercised.
 */
class HttpWeatherOrigin extends Origin
{
    public static function getAlias(): string
    {
        return 'http_weather';
    }

    public function fetch(array $arguments = []): array
    {
        return $this->http()->get('https://api.example.test/weather')->throw()->json();
    }

    /** The timeout the client was built with. */
    public function resolvedTimeout(): int
    {
        return (int) ($this->config('timeout') ?? $this->timeout);
    }
}
