<?php

namespace EduLazaro\Larasources\Tests;

use EduLazaro\Larasources\Models\SourceArgument;
use EduLazaro\Larasources\Tests\Fixtures\City;
use EduLazaro\Larasources\Tests\Fixtures\FakeWeatherOrigin;
use EduLazaro\Larasources\Tests\Fixtures\WeatherSource;
use RuntimeException;

class SourceCompositionTest extends TestCase
{
    protected function city(): City
    {
        return City::create(['name' => 'Vigo', 'external_id' => 'city-42']);
    }

    public function test_an_argument_pointing_at_another_source_hands_over_its_payload(): void
    {
        $city = $this->city();

        $current = $city->source('weather', null, 'current')->fetch();

        // Declared before this one has a row of its own, which is when the
        // origin needs it.
        $city->source('weather', null, 'forecast')
            ->useArgument('previous', $current)
            ->fetch();

        $this->assertSame(['temperature' => 21.5], FakeWeatherOrigin::$lastFetchArguments['previous']);
    }

    public function test_the_pointer_is_stored_so_a_later_read_still_resolves_it(): void
    {
        $city = $this->city();

        $current = $city->source('weather', null, 'current')->fetch();

        $city->source('weather', null, 'forecast')
            ->useArgument('previous', $current)
            ->fetch();

        $this->assertSame(1, SourceArgument::count());

        // A fresh instance, nothing declared on it
        $arguments = $city->source('weather', null, 'forecast')->resolveArguments();

        $this->assertSame(['temperature' => 21.5], $arguments['previous']);
    }

    public function test_it_is_a_pointer_and_not_a_copy(): void
    {
        $city = $this->city();

        $current = $city->source('weather', null, 'current')->fetch();

        $city->source('weather', null, 'forecast')
            ->useArgument('previous', $current)
            ->fetch();

        // The source it points at changes
        $current->fill(['temperature' => -5.5])->persist();

        $arguments = $city->source('weather', null, 'forecast')->resolveArguments();

        // Straight from the other source's payload, uncast: casts belong to the
        // source that owns those fields, not to whoever reads them as arguments.
        $this->assertSame(['temperature' => -5.5], $arguments['previous']);
    }

    public function test_a_name_points_at_one_thing_only(): void
    {
        $city = $this->city();

        $current = $city->source('weather', null, 'current')->fetch();
        $other = $city->source('weather', null, 'yesterday')->fetch();

        $city->source('weather', null, 'forecast')
            ->useArgument('previous', $current)
            ->useArgument('previous', $other)
            ->fetch();

        $this->assertSame(1, SourceArgument::count());
        $this->assertSame($other->record()->getKey(), SourceArgument::firstOrFail()->argumentable_id);
    }

    public function test_an_argument_can_point_at_a_model(): void
    {
        $city = $this->city();

        $city->source('weather', null, 'forecast')->useArgument('city', $city)->fetch();

        $this->assertTrue(FakeWeatherOrigin::$lastFetchArguments['city']->is($city));
    }

    public function test_forgetting_removes_the_pointer_and_its_row(): void
    {
        $city = $this->city();

        $current = $city->source('weather', null, 'current')->fetch();

        $forecast = $city->source('weather', null, 'forecast')
            ->useArgument('previous', $current)
            ->fetch();

        $this->assertSame(1, SourceArgument::count());

        $forecast->forgetArgument('previous');

        $this->assertSame(0, SourceArgument::count());
        $this->assertArrayNotHasKey('previous', $city->source('weather', null, 'forecast')->resolveArguments());
    }

    public function test_pointing_at_a_source_that_was_never_stored_says_so(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('has no record yet');

        $this->city()->source('weather')->useArgument('previous', new WeatherSource());
    }
}
