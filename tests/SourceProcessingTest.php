<?php

namespace EduLazaro\Larasources\Tests;

use EduLazaro\Larasources\Enums\OriginStatus;
use EduLazaro\Larasources\Exceptions\OriginException;
use EduLazaro\Larasources\Models\SourceRecord;
use EduLazaro\Larasources\OriginResult;
use EduLazaro\Larasources\Tests\Fixtures\City;
use EduLazaro\Larasources\Tests\Fixtures\FakeWeatherOrigin;
use EduLazaro\Larasources\Tests\Fixtures\WeatherSource;

class SourceProcessingTest extends TestCase
{
    protected function city(): City
    {
        return City::create(['name' => 'Vigo', 'external_id' => 'city-42']);
    }

    protected function cityWithProcessingRecord(): City
    {
        FakeWeatherOrigin::$saveResult = new OriginResult(status: OriginStatus::Processing);

        $city = $this->city();
        $city->source('weather')->trySave();

        FakeWeatherOrigin::$saveResult = null;

        return $city;
    }

    public function test_reading_a_processing_record_never_calls_the_origin(): void
    {
        $city = $this->cityWithProcessingRecord();

        $temperature = $city->source('weather')->temperature;

        $this->assertSame(0, FakeWeatherOrigin::$fetchCalls);
        $this->assertSame(18.0, $temperature);
        $this->assertSame(OriginStatus::Processing, SourceRecord::firstOrFail()->status);
    }

    public function test_reconciling_asks_the_origin_again_and_stores_what_it_has(): void
    {
        $city = $this->cityWithProcessingRecord();

        $source = $city->source('weather')->reconcile();

        $record = SourceRecord::firstOrFail();

        $this->assertSame(1, FakeWeatherOrigin::$fetchCalls);
        $this->assertSame(21.5, $source->temperature);
        $this->assertEquals(['temperature' => 21.5], $record->attributes);
        $this->assertSame(OriginStatus::Saved, $record->status);
        $this->assertSame(1, SourceRecord::count());
    }

    public function test_reconciling_a_saved_record_does_nothing(): void
    {
        $city = $this->city();
        $city->source('weather')->save();

        $city->source('weather')->reconcile();

        $this->assertSame(0, FakeWeatherOrigin::$fetchCalls);
        $this->assertEquals(['temperature' => 18.0], SourceRecord::firstOrFail()->attributes);
    }

    public function test_reconciling_a_source_that_was_never_stored_does_nothing(): void
    {
        $this->city()->source('weather')->reconcile();

        $this->assertSame(0, FakeWeatherOrigin::$fetchCalls);
        $this->assertSame(0, SourceRecord::count());
    }

    public function test_a_failure_while_reconciling_is_the_callers_to_handle(): void
    {
        $city = $this->cityWithProcessingRecord();

        FakeWeatherOrigin::$fetchException = new OriginException('The feed is down');

        try {
            $city->source('weather')->reconcile();
            $this->fail('reconcile() must not swallow the failure');
        } catch (OriginException $e) {
            $this->assertSame('The feed is down', $e->getMessage());
        }

        $record = SourceRecord::firstOrFail();

        $this->assertEquals(['temperature' => 18.0], $record->attributes);
        $this->assertSame(OriginStatus::Processing, $record->status);
    }

    public function test_the_records_waiting_to_be_reconciled_are_a_query(): void
    {
        $this->cityWithProcessingRecord();

        $pending = SourceRecord::processing()->get();

        $this->assertCount(1, $pending);

        foreach ($pending as $record) {
            $record->toSource(WeatherSource::class)->reconcile();
        }

        $this->assertSame(0, SourceRecord::processing()->count());
        $this->assertSame(OriginStatus::Saved, SourceRecord::firstOrFail()->status);
    }

    public function test_reconciling_leaves_the_record_loaded_so_it_can_be_chained(): void
    {
        $city = $this->city();
        $city->source('weather')->save();

        $record = $city->source('weather')->reconcile()->record();

        $this->assertNotNull($record);
        $this->assertSame(OriginStatus::Saved, $record->status);
        $this->assertSame(0, FakeWeatherOrigin::$fetchCalls);

        $reconciled = $this->cityWithProcessingRecord()->source('weather')->reconcile()->record();

        $this->assertSame(OriginStatus::Saved, $reconciled->status);
    }

    public function test_the_scope_leaves_saved_records_out(): void
    {
        $this->city()->source('weather')->save();

        $this->assertSame(0, SourceRecord::processing()->count());
        $this->assertSame(1, SourceRecord::count());
    }
}
