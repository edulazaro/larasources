<?php

namespace EduLazaro\Larasources\Tests;

use EduLazaro\Larasources\Models\SourceRecord;
use EduLazaro\Larasources\Tests\Fixtures\City;
use EduLazaro\Larasources\Tests\Fixtures\FakeWeatherOrigin;
use EduLazaro\Larasources\Tests\Fixtures\WeatherSource;

class SourceCacheTest extends TestCase
{
    protected function city(): City
    {
        return City::create([
            'name' => 'Vigo',
            'external_id' => 'city-42',
            'region_code' => 'GA',
        ]);
    }

    protected function record(City $city): ?SourceRecord
    {
        return SourceRecord::where('sourceable_type', $city->getMorphClass())
            ->where('sourceable_id', $city->getKey())
            ->where('name', 'weather')
            ->first();
    }

    public function test_fetching_from_the_origin_caches_the_result(): void
    {
        $city = $this->city();

        $this->assertNull($this->record($city));

        $source = $city->source('weather')->fetch();

        $record = $this->record($city);

        $this->assertNotNull($record);
        $this->assertEquals(['temperature' => 21.5], $record->attributes);
        $this->assertSame(md5(json_encode(['temperature' => 21.5])), $record->signature);
        $this->assertSame($record->getKey(), $source->record()->getKey());
    }

    public function test_fetching_again_refreshes_the_cached_result(): void
    {
        $city = $this->city();

        SourceRecord::create([
            'sourceable_type' => $city->getMorphClass(),
            'sourceable_id' => $city->getKey(),
            'name' => 'weather',
            'attributes' => ['temperature' => -5.0],
            'signature' => 'stale',
        ]);

        $city->source('weather')->fetch();

        $record = $this->record($city);

        $this->assertEquals(['temperature' => 21.5], $record->attributes);
        $this->assertNotSame('stale', $record->signature);
        $this->assertSame(1, SourceRecord::count());
    }

    public function test_fetching_caches_each_variant_apart(): void
    {
        $city = $this->city();

        $city->source('weather', null, 'current')->fetch();
        $city->source('weather', null, 'forecast')->fetch();

        $this->assertSame(['current', 'forecast'], SourceRecord::orderBy('variant')->pluck('variant')->all());
    }

    public function test_fetching_a_detached_source_does_not_touch_the_database(): void
    {
        $source = (new WeatherSource())->fetch();

        $this->assertSame(21.5, $source->temperature);
        $this->assertNull($source->record());
        $this->assertSame(0, SourceRecord::count());
    }

    public function test_reading_an_attribute_uses_the_cache_without_calling_the_origin(): void
    {
        $city = $this->city();

        SourceRecord::create([
            'sourceable_type' => $city->getMorphClass(),
            'sourceable_id' => $city->getKey(),
            'name' => 'weather',
            'attributes' => ['temperature' => -5.0],
        ]);

        FakeWeatherOrigin::$lastFetchArguments = [];

        $this->assertSame(-5.0, $city->source('weather')->temperature);
        $this->assertSame([], FakeWeatherOrigin::$lastFetchArguments);
    }

    public function test_reading_an_attribute_without_cache_falls_through_to_the_origin_and_caches_it(): void
    {
        $city = $this->city();

        $this->assertSame(21.5, $city->source('weather')->temperature);
        $this->assertEquals(['temperature' => 21.5], $this->record($city)->attributes);
    }

    public function test_the_cached_record_keeps_the_model_the_source_was_built_from(): void
    {
        $city = $this->city();
        $source = $city->source('weather');

        $source->fetch();

        $this->assertSame($city, $source->getSourceable());
    }
}
