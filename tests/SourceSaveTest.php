<?php

namespace EduLazaro\Larasources\Tests;

use EduLazaro\Larasources\Enums\OriginStatus;
use EduLazaro\Larasources\Exceptions\OriginException;
use EduLazaro\Larasources\Models\SourceRecord;
use EduLazaro\Larasources\OriginResult;
use EduLazaro\Larasources\Tests\Fixtures\City;
use EduLazaro\Larasources\Tests\Fixtures\FakeWeatherOrigin;

class SourceSaveTest extends TestCase
{
    protected function city(): City
    {
        return City::create(['name' => 'Vigo', 'external_id' => 'city-42']);
    }

    public function test_a_save_the_origin_takes_is_stored_as_saved(): void
    {
        $this->city()->source('weather')->save();

        $record = SourceRecord::firstOrFail();

        $this->assertSame(OriginStatus::Saved, $record->status);
        $this->assertEquals(['temperature' => 18.0], $record->attributes);
    }

    public function test_try_save_returns_the_outcome_and_persists_it(): void
    {
        $result = $this->city()->source('weather')->trySave();

        $this->assertTrue($result->ok());
        $this->assertFalse($result->processing());
        $this->assertSame(OriginStatus::Saved, $result->status);
        $this->assertEquals(['temperature' => 18.0], $result->data);
        $this->assertSame(1, SourceRecord::count());
    }

    public function test_an_origin_can_report_that_the_service_has_not_finished(): void
    {
        FakeWeatherOrigin::$saveResult = new OriginResult(
            status: OriginStatus::Processing,
            data: ['external_id' => 'ad-1'],
            message: 'Pending review',
        );

        $result = $this->city()->source('weather')->trySave();

        $this->assertTrue($result->processing());
        $this->assertTrue($result->ok());
        $this->assertSame('Pending review', $result->message);
        $this->assertSame(OriginStatus::Processing, SourceRecord::firstOrFail()->status);
    }

    public function test_try_save_reports_a_failure_instead_of_throwing_and_stores_nothing(): void
    {
        FakeWeatherOrigin::$saveException = new OriginException('The service refused the data');

        $result = $this->city()->source('weather')->trySave();

        $this->assertTrue($result->failed());
        $this->assertFalse($result->ok());
        $this->assertSame('The service refused the data', $result->message);
        $this->assertInstanceOf(OriginException::class, $result->exception);
        $this->assertSame(0, SourceRecord::count());
    }

    public function test_save_lets_the_failure_through_so_a_queued_job_can_retry(): void
    {
        FakeWeatherOrigin::$saveException = new OriginException('The service refused the data');

        try {
            $this->city()->source('weather')->save();
            $this->fail('save() must not swallow the failure');
        } catch (OriginException $e) {
            $this->assertSame('The service refused the data', $e->getMessage());
        }

        $this->assertSame(0, SourceRecord::count());
    }

    public function test_a_failed_result_is_not_stored_either(): void
    {
        FakeWeatherOrigin::$saveResult = new OriginResult(
            status: OriginStatus::Failed,
            message: 'Rejected',
        );

        $result = $this->city()->source('weather')->trySave();

        $this->assertTrue($result->failed());
        $this->assertSame(0, SourceRecord::count());
    }

    public function test_save_turns_a_failed_result_into_an_exception(): void
    {
        FakeWeatherOrigin::$saveResult = new OriginResult(
            status: OriginStatus::Failed,
            message: 'Rejected',
        );

        $this->expectException(OriginException::class);
        $this->expectExceptionMessage('Rejected');

        $this->city()->source('weather')->save();
    }

    public function test_what_was_already_stored_survives_a_failed_save(): void
    {
        $city = $this->city();
        $city->source('weather')->fetch();

        FakeWeatherOrigin::$saveException = new OriginException('The service is down');

        try {
            $city->source('weather')->save();
        } catch (OriginException $e) {
            // The job would retry here.
        }

        $this->assertSame(1, SourceRecord::count());
        $this->assertEquals(['temperature' => 21.5], SourceRecord::firstOrFail()->attributes);
    }

    public function test_the_payload_still_reaches_the_origin_untouched(): void
    {
        $this->city()->source('weather')->trySave();

        $this->assertEquals(['temperature' => 18.0], FakeWeatherOrigin::$lastSavedPayload);
    }
}
