<?php

namespace EduLazaro\Larasources\Tests;

use EduLazaro\Larasources\Enums\OriginStatus;
use EduLazaro\Larasources\Exceptions\OriginException;
use EduLazaro\Larasources\Models\SourceRecord;
use EduLazaro\Larasources\OriginResult;
use EduLazaro\Larasources\Tests\Fixtures\City;
use EduLazaro\Larasources\Tests\Fixtures\FakeWeatherOrigin;

class SourceProcessingTest extends TestCase
{
    protected function city(): City
    {
        return City::create(['name' => 'Vigo', 'external_id' => 'city-42']);
    }

    protected function cityWithProcessingRecord(string $source = 'weather'): City
    {
        FakeWeatherOrigin::$saveResult = new OriginResult(status: OriginStatus::Processing);

        $city = $this->city();
        $city->source($source)->trySave();

        FakeWeatherOrigin::$saveResult = null;

        return $city;
    }

    public function test_reading_a_stale_processing_record_asks_the_origin_again(): void
    {
        $city = $this->cityWithProcessingRecord();

        SourceRecord::query()->update(['updated_at' => now()->subHour()]);

        $temperature = $city->source('weather')->temperature;

        $record = SourceRecord::firstOrFail();

        $this->assertSame(1, FakeWeatherOrigin::$fetchCalls);
        $this->assertSame(21.5, $temperature);
        $this->assertEquals(['temperature' => 21.5], $record->attributes);
        $this->assertSame(OriginStatus::Saved, $record->status);
        $this->assertSame(1, SourceRecord::count());
    }

    public function test_a_processing_record_is_trusted_inside_its_window(): void
    {
        $city = $this->cityWithProcessingRecord();

        $temperature = $city->source('weather')->temperature;

        $this->assertSame(0, FakeWeatherOrigin::$fetchCalls);
        $this->assertSame(18.0, $temperature);
        $this->assertSame(OriginStatus::Processing, SourceRecord::firstOrFail()->status);
    }

    public function test_the_window_is_declared_by_each_source(): void
    {
        $city = $this->cityWithProcessingRecord('weather_eager');

        $temperature = $city->source('weather_eager')->temperature;

        $this->assertSame(1, FakeWeatherOrigin::$fetchCalls);
        $this->assertSame(21.5, $temperature);
    }

    public function test_a_saved_record_never_goes_back_to_the_origin(): void
    {
        $city = $this->city();
        $city->source('weather')->save();

        SourceRecord::query()->update(['updated_at' => now()->subYear()]);

        $this->assertSame(18.0, $city->source('weather')->temperature);
        $this->assertSame(0, FakeWeatherOrigin::$fetchCalls);
    }

    public function test_an_origin_that_cannot_answer_leaves_the_record_as_it_was(): void
    {
        $city = $this->cityWithProcessingRecord();

        SourceRecord::query()->update(['updated_at' => now()->subHour()]);
        FakeWeatherOrigin::$fetchException = new OriginException('The feed is down');

        $temperature = $city->source('weather')->temperature;

        $record = SourceRecord::firstOrFail();

        $this->assertSame(1, FakeWeatherOrigin::$fetchCalls);
        $this->assertSame(18.0, $temperature);
        $this->assertEquals(['temperature' => 18.0], $record->attributes);
        $this->assertSame(OriginStatus::Processing, $record->status);
        $this->assertSame(1, SourceRecord::count());
    }

    public function test_the_processing_records_waiting_to_be_reconciled_are_a_query(): void
    {
        $this->cityWithProcessingRecord();

        SourceRecord::query()->update(['updated_at' => now()->subHours(6)]);

        $pending = SourceRecord::query()
            ->where('status', OriginStatus::Processing)
            ->where('updated_at', '<', now()->subHour())
            ->get();

        $this->assertCount(1, $pending);
    }
}
