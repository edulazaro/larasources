<?php

namespace EduLazaro\Larasources\Tests;

use EduLazaro\Larasources\Models\SourceRecord;
use EduLazaro\Larasources\Source;
use EduLazaro\Larasources\Tests\Fixtures\City;
use EduLazaro\Larasources\Tests\Fixtures\FakeWeatherOrigin;
use EduLazaro\Larasources\Tests\Fixtures\WeatherSource;
use RuntimeException;

class SourceLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        FakeWeatherOrigin::$lastSavedPayload = [];
        FakeWeatherOrigin::$deleted = false;
    }

    protected function city(): City
    {
        return City::create(['name' => 'Vigo', 'external_id' => 'city-42']);
    }

    public function test_saving_builds_the_payload_pushes_it_and_caches_it(): void
    {
        $city = $this->city();

        $city->source('weather')->save();

        $this->assertEquals(['temperature' => 18.0], FakeWeatherOrigin::$lastSavedPayload);
        $this->assertEquals(['temperature' => 18.0], SourceRecord::firstOrFail()->attributes);
    }

    public function test_clearing_removes_the_cached_record_only(): void
    {
        $city = $this->city();
        $source = $city->source('weather')->fetch();

        $this->assertSame(1, SourceRecord::count());

        $this->assertTrue($source->clear());
        $this->assertSame(0, SourceRecord::count());
        $this->assertFalse(FakeWeatherOrigin::$deleted);
    }

    public function test_clearing_a_source_that_was_never_cached_returns_false(): void
    {
        $this->assertFalse($this->city()->source('weather')->clear());
    }

    public function test_deleting_removes_it_from_the_origin_and_from_the_cache(): void
    {
        $city = $this->city();
        $source = $city->source('weather')->fetch();

        $this->assertTrue($source->delete());
        $this->assertTrue(FakeWeatherOrigin::$deleted);
        $this->assertSame(0, SourceRecord::count());
    }

    public function test_a_mocked_source_replaces_the_real_one(): void
    {
        $city = $this->city();

        $mock = (new WeatherSource())->fill(['temperature' => -40.0]);
        $city->mockSource(WeatherSource::class, $mock);

        $this->assertSame(-40.0, $city->source('weather')->temperature);
        $this->assertSame(0, SourceRecord::count());
    }

    public function test_a_source_without_the_uses_origin_attribute_fails_loudly(): void
    {
        $source = new class extends Source {
            protected $fillable = ['temperature'];
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing #[UsesOrigin]');

        $source->origin();
    }

    public function test_an_unknown_source_alias_fails_loudly(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->city()->source('tides');
    }

    public function test_the_record_can_be_turned_back_into_a_source(): void
    {
        $city = $this->city();
        $city->source('weather')->fetch();

        $source = SourceRecord::firstOrFail()->toSource(WeatherSource::class);

        $this->assertInstanceOf(WeatherSource::class, $source);
        $this->assertSame(21.5, $source->temperature);
        $this->assertTrue($source->getSourceable()->is($city));
    }
}
