<?php

namespace EduLazaro\Larasources\Tests;

use EduLazaro\Larasources\Tests\Fixtures\HttpWeatherOrigin;
use EduLazaro\Larasources\Tests\Fixtures\WeatherSource;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class OriginConfigTest extends TestCase
{
    public function test_an_origin_still_built_around_get_config_says_so(): void
    {
        $this->expectException(\EduLazaro\Larasources\Exceptions\OriginException::class);
        $this->expectExceptionMessage('Rename it to config()');

        new class (new \EduLazaro\Larasources\Tests\Fixtures\WeatherSource()) extends \EduLazaro\Larasources\Origins\Origin {
            public static function getAlias(): string
            {
                return 'legacy';
            }

            public function fetch(array $arguments = []): array
            {
                return [];
            }

            protected function getConfig(?string $key = null, mixed $default = null): mixed
            {
                return 'from the database';
            }
        };
    }

    protected function origin(): HttpWeatherOrigin
    {
        return new HttpWeatherOrigin(new WeatherSource());
    }

    public function test_an_origin_without_any_configuration_works(): void
    {
        Http::fake(['*' => Http::response(['temperature' => 21.5], 200)]);

        $this->assertSame([], config('larasources', []));
        $this->assertSame(['temperature' => 21.5], $this->origin()->fetch());
    }

    public function test_the_origin_config_falls_back_to_the_given_default(): void
    {
        $origin = $this->origin();

        $this->assertSame('fallback', $this->invokeMethod($origin, 'config', 'api_key', 'fallback'));
        $this->assertNull($this->invokeMethod($origin, 'config', 'api_key'));
    }

    public function test_an_origin_reads_its_credentials_from_its_own_config_block(): void
    {
        config(['larasources.origins.http_weather.api_key' => 'secret-key']);

        $this->assertSame('secret-key', $this->invokeMethod($this->origin(), 'config', 'api_key'));
    }

    public function test_without_retry_configuration_the_request_is_attempted_once(): void
    {
        Http::fake(['*' => Http::response(['error' => 'boom'], 500)]);

        $this->expectException(RequestException::class);

        try {
            $this->origin()->fetch();
        } finally {
            Http::assertSentCount(1);
        }
    }

    public function test_the_origin_retries_as_many_times_as_its_own_config_says(): void
    {
        config(['larasources.origins.http_weather.retry' => ['attempts' => 3, 'delay' => 0]]);

        Http::fake([
            '*' => Http::sequence()
                ->push(['error' => 'boom'], 500)
                ->push(['error' => 'boom'], 500)
                ->push(['temperature' => 21.5], 200),
        ]);

        $this->assertSame(['temperature' => 21.5], $this->origin()->fetch());

        Http::assertSentCount(3);
    }

    public function test_a_source_reads_its_own_config_block(): void
    {
        config(['larasources.sources.weather' => ['units' => 'metric', 'max_days' => 7]]);

        $source = (new \EduLazaro\Larasources\Tests\Fixtures\City([
            'name' => 'Vigo',
        ]))->source('weather');

        $this->assertSame('metric', $source->config('units'));
        $this->assertSame(7, $source->config('max_days'));
        $this->assertSame(['units' => 'metric', 'max_days' => 7], $source->config());
        $this->assertSame('fallback', $source->config('missing', 'fallback'));
    }

    public function test_the_source_config_block_follows_the_name_it_is_mapped_under(): void
    {
        $city = \EduLazaro\Larasources\Tests\Fixtures\City::create(['name' => 'Vigo']);

        $this->assertSame('weather', $city->source('weather')->name());

        // Not mapped: the class basename is the fallback
        $this->assertSame('WeatherSource', (new WeatherSource())->name());
    }

    public function test_the_timeout_comes_from_the_origin_config_when_set(): void
    {
        $this->assertSame(30, $this->origin()->resolvedTimeout());

        config(['larasources.origins.http_weather.timeout' => 5]);

        $this->assertSame(5, $this->origin()->resolvedTimeout());
    }
}
