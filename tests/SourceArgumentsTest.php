<?php

namespace EduLazaro\Larasources\Tests;

use EduLazaro\Larasources\Models\SourceArgument;
use EduLazaro\Larasources\Models\SourceRecord;
use EduLazaro\Larasources\Tests\Fixtures\City;
use EduLazaro\Larasources\Tests\Fixtures\FakeWeatherOrigin;
use EduLazaro\Larasources\Tests\Fixtures\WeatherSource;

class SourceArgumentsTest extends TestCase
{
    protected function city(array $attributes = []): City
    {
        return City::create(array_merge([
            'name' => 'Vigo',
            'external_id' => 'city-42',
            'region_code' => 'GA',
        ], $attributes));
    }

    public function test_it_resolves_the_argument_map_into_sourceable_values(): void
    {
        $city = $this->city();

        $this->assertSame([
            'region' => 'GA',
            'city_id' => 'city-42',
        ], $city->source('weather')->resolveArguments());
    }

    public function test_it_resolves_a_missing_attribute_to_null_instead_of_the_attribute_name(): void
    {
        $city = $this->city(['external_id' => null]);

        $arguments = $city->source('weather')->resolveArguments();

        $this->assertNull($arguments['city_id']);
        $this->assertNotSame('external_id', $arguments['city_id']);
    }

    public function test_it_resolves_to_null_without_a_sourceable(): void
    {
        $this->assertSame([
            'region' => null,
            'city_id' => null,
        ], (new WeatherSource())->resolveArguments());
    }

    public function test_runtime_arguments_take_precedence_over_the_map(): void
    {
        $city = $this->city();

        $arguments = $city->source('weather', ['city_id' => 'override'])->resolveArguments();

        $this->assertSame('override', $arguments['city_id']);
        $this->assertSame('GA', $arguments['region']);
    }

    public function test_the_stored_record_takes_precedence_over_the_map(): void
    {
        $city = $this->city();

        $record = SourceRecord::create([
            'sourceable_type' => $city->getMorphClass(),
            'sourceable_id' => $city->getKey(),
            'name' => 'weather',
            'variant' => null,
            'arguments' => ['city_id' => 'stored'],
            'attributes' => [],
        ]);

        $source = $city->source('weather')->setRecord($record);

        $this->assertSame('stored', $source->resolveArguments()['city_id']);
    }

    public function test_an_attached_source_argument_overrides_the_map(): void
    {
        $city = $this->city();

        $record = SourceRecord::create([
            'sourceable_type' => $city->getMorphClass(),
            'sourceable_id' => $city->getKey(),
            'name' => 'weather',
            'attributes' => [],
        ]);

        SourceArgument::create([
            'name' => 'city_id',
            'source_id' => $record->getKey(),
            'argumentable_type' => $city->getMorphClass(),
            'argumentable_id' => $city->getKey(),
        ]);

        $arguments = $city->source('weather')->setRecord($record->fresh())->resolveArguments();

        $this->assertInstanceOf(City::class, $arguments['city_id']);
        $this->assertTrue($arguments['city_id']->is($city));
    }

    public function test_fetch_passes_resolved_values_to_the_origin(): void
    {
        FakeWeatherOrigin::$lastFetchArguments = [];

        $city = $this->city();
        $source = $city->source('weather')->fetch();

        $this->assertSame([
            'region' => 'GA',
            'city_id' => 'city-42',
        ], FakeWeatherOrigin::$lastFetchArguments);

        $this->assertSame(21.5, $source->temperature);
    }

    public function test_persist_keeps_the_arguments_stored_on_the_record(): void
    {
        $city = $this->city();

        SourceRecord::create([
            'sourceable_type' => $city->getMorphClass(),
            'sourceable_id' => $city->getKey(),
            'name' => 'weather',
            'arguments' => ['city_id' => 'stored'],
            'attributes' => [],
        ]);

        $source = $city->source('weather');
        $source->build();
        $source->persist();

        $record = SourceRecord::where('sourceable_id', $city->getKey())->firstOrFail();

        $this->assertSame(['city_id' => 'stored'], $record->arguments);
        $this->assertSame('stored', $source->resolveArguments()['city_id']);
    }

    public function test_persist_stores_the_signature_of_the_built_source(): void
    {
        $city = $this->city();

        $source = $city->source('weather');
        $source->build();
        $source->persist();

        $record = SourceRecord::where('sourceable_id', $city->getKey())->firstOrFail();

        $this->assertSame(md5(json_encode(['temperature' => 18.0])), $record->signature);
        $this->assertSame('fake_weather', $record->origin);
        $this->assertEquals(['temperature' => 18.0], $record->attributes);
    }
}
