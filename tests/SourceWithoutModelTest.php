<?php

namespace EduLazaro\Larasources\Tests;

use EduLazaro\Larasources\Models\SourceRecord;
use EduLazaro\Larasources\Tests\Fixtures\City;
use EduLazaro\Larasources\Tests\Fixtures\WeatherSource;
use RuntimeException;

class SourceWithoutModelTest extends TestCase
{
    protected function city(): City
    {
        return City::create(['name' => 'Vigo', 'external_id' => 'city-42']);
    }

    public function test_an_external_id_is_enough_identity_to_store_a_source(): void
    {
        (new WeatherSource())->setExternalId('beat-42')->fetch();

        $record = SourceRecord::firstOrFail();

        $this->assertNull($record->sourceable_type);
        $this->assertNull($record->sourceable_id);
        $this->assertSame('beat-42', $record->external_id);
        $this->assertEquals(['temperature' => 21.5], $record->attributes);
    }

    public function test_with_no_model_and_no_external_id_nothing_is_stored(): void
    {
        $source = (new WeatherSource())->fetch();

        $this->assertSame(21.5, $source->temperature);
        $this->assertSame(0, SourceRecord::count());
    }

    public function test_the_same_external_id_updates_its_row_instead_of_adding_one(): void
    {
        (new WeatherSource())->setExternalId('beat-42')->fetch();
        (new WeatherSource())->setExternalId('beat-42')->fetch();

        $this->assertSame(1, SourceRecord::count());
    }

    public function test_each_external_id_gets_its_own_row(): void
    {
        (new WeatherSource())->setExternalId('beat-42')->fetch();
        (new WeatherSource())->setExternalId('beat-43')->fetch();

        $this->assertSame(['beat-42', 'beat-43'], SourceRecord::orderBy('external_id')->pluck('external_id')->all());
    }

    public function test_a_source_with_no_model_reads_its_own_row_back(): void
    {
        (new WeatherSource())->setExternalId('beat-42')->fetch();

        $source = (new WeatherSource())->setExternalId('beat-42');

        $this->assertSame(21.5, $source->temperature);
        $this->assertNotNull($source->record());
        $this->assertSame('beat-42', $source->externalId());
    }

    public function test_attaching_gives_the_row_its_model_and_keeps_everything_else(): void
    {
        $source = (new WeatherSource())->setExternalId('beat-42')->fetch();

        $city = $this->city();
        $source->attachTo($city);

        $record = SourceRecord::firstOrFail();

        $this->assertSame(1, SourceRecord::count());
        $this->assertTrue($record->sourceable->is($city));
        $this->assertSame('beat-42', $record->external_id);

        // And the model finds it from then on.
        $this->assertSame(21.5, $city->source('weather')->temperature);
        $this->assertTrue($city->source('weather')->record()->is($record));
    }

    public function test_attaching_where_the_model_already_has_that_source_is_the_callers_problem(): void
    {
        $city = $this->city();
        $city->source('weather')->fetch();

        $scraped = (new WeatherSource())->setExternalId('beat-42')->fetch();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already has a `weather` source');

        $scraped->attachTo($city);
    }

    public function test_attaching_re_keys_the_row_to_the_name_its_owner_uses(): void
    {
        $source = (new WeatherSource())->setExternalId('beat-42')->fetch();

        // With nobody naming it, a source is named after its class.
        $this->assertSame('WeatherSource', SourceRecord::firstOrFail()->name);

        $source->attachTo($this->city());

        // Its owner maps it under `weather`, which is what it will look it up by.
        $this->assertSame('weather', SourceRecord::firstOrFail()->name);
        $this->assertSame(1, SourceRecord::count());
    }

    public function test_a_source_can_state_its_own_name_for_when_no_model_names_it(): void
    {
        $source = new #[\EduLazaro\Larasources\Attributes\UsesOrigin(\EduLazaro\Larasources\Tests\Fixtures\FakeWeatherOrigin::class)] class extends WeatherSource {
            protected ?string $sourceName = 'mega';
        };

        $source->setExternalId('beat-42')->fetch();

        $this->assertSame('mega', SourceRecord::firstOrFail()->name);
    }

    public function test_the_rows_waiting_for_a_model_are_a_query(): void
    {
        (new WeatherSource())->setExternalId('beat-42')->fetch();
        $this->city()->source('weather')->fetch();

        $this->assertSame(2, SourceRecord::count());
        $this->assertSame(1, SourceRecord::unattached()->count());
        $this->assertSame('beat-42', SourceRecord::unattached()->first()->external_id);
    }
}
