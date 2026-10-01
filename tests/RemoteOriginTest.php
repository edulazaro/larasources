<?php

namespace EduLazaro\Larasources\Tests;

use EduLazaro\Larasources\Exceptions\OriginException;
use EduLazaro\Larasources\Models\SourceRecord;
use EduLazaro\Larasources\Tests\Fixtures\City;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class RemoteOriginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('larasources.origins.http_listing.api_token', 'token-1');
    }

    protected function city(): City
    {
        return City::create(['name' => 'Vigo', 'external_id' => 'city-42']);
    }

    public function test_a_resource_that_does_not_exist_yet_is_created(): void
    {
        Http::fake(['listings.test/listings' => Http::response(['id' => 'L-1', 'title' => 'A flat with a view'])]);

        $this->city()->source('listing')->save();

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === 'https://listings.test/listings'
            && $request->hasHeader('Authorization', 'Bearer token-1'));

        // What the service called it comes back on the record, so the next write updates.
        $this->assertSame('L-1', SourceRecord::firstOrFail()->external_id);
    }

    public function test_a_resource_the_service_already_has_is_updated(): void
    {
        Http::fake([
            'listings.test/listings' => Http::response(['id' => 'L-1']),
            'listings.test/listings/L-1' => Http::response(['id' => 'L-1']),
        ]);

        $city = $this->city();
        $city->source('listing')->save();
        $city->source('listing')->save();

        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && $request->url() === 'https://listings.test/listings/L-1');

        $this->assertSame(1, SourceRecord::count());
    }

    public function test_a_refused_save_throws_and_stores_nothing(): void
    {
        Http::fake(['listings.test/*' => Http::response(['error' => 'nope'], 422)]);

        $this->expectException(OriginException::class);

        try {
            $this->city()->source('listing')->save();
        } finally {
            $this->assertSame(0, SourceRecord::count());
        }
    }

    public function test_deleting_something_the_service_no_longer_has_counts_as_deleted(): void
    {
        Http::fake([
            'listings.test/listings' => Http::response(['id' => 'L-1']),
            'listings.test/listings/*' => Http::response(['error' => 'gone'], 404),
        ]);

        $city = $this->city();
        $city->source('listing')->save();

        $this->assertTrue($city->source('listing')->delete());
        $this->assertSame(0, SourceRecord::count());
    }

    public function test_a_delete_that_fails_keeps_the_record(): void
    {
        Http::fake([
            'listings.test/listings' => Http::response(['id' => 'L-1']),
            'listings.test/listings/*' => Http::response(['error' => 'boom'], 500),
        ]);

        $city = $this->city();
        $city->source('listing')->save();

        $this->expectException(OriginException::class);

        try {
            $city->source('listing')->delete();
        } finally {
            $this->assertSame(1, SourceRecord::count());
        }
    }

    public function test_missing_credentials_are_a_configuration_error_not_a_request(): void
    {
        Config::set('larasources.origins.http_listing.api_token', null);

        Http::fake();

        $this->expectException(OriginException::class);
        $this->expectExceptionMessage('is not properly configured');

        try {
            $this->city()->source('listing')->save();
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_reading_goes_through_the_endpoint_and_fills_the_source(): void
    {
        Http::fake(['listings.test/listings/*' => Http::response(['title' => 'A flat with a view'])]);

        $city = $this->city();
        $city->source('listing')->setVariantArguments(['id' => 'L-1'])->fetch();

        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && $request->url() === 'https://listings.test/listings/L-1');

        $this->assertSame('A flat with a view', $city->source('listing')->title);
    }
}
