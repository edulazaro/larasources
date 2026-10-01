<?php

namespace EduLazaro\Larasources\Tests;

use EduLazaro\Larasources\Exceptions\OriginException;
use EduLazaro\Larasources\Tests\Fixtures\City;
use EduLazaro\Larasources\Tests\Fixtures\CopywriterOrigin;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class BundledOriginsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CopywriterOrigin::$useProvider = 'anthropic';
        Config::set('larasources.origins.copywriter.api_key', 'key-1');
    }

    protected function city(): City
    {
        return City::create(['name' => 'Vigo', 'external_id' => 'city-42']);
    }

    public function test_the_scraper_origin_reads_the_selectors_out_of_the_page(): void
    {
        Http::fake(['recipes.test/*' => Http::response(
            '<html><body><h1>Pulpo á feira</h1><span class="author">Edu</span>'
            . '<div class="card servings">4</div><div id="origin"><span>Galicia</span></div></body></html>'
        )]);

        $source = $this->city()
            ->source('recipe')
            ->setVariantArguments(['url' => 'https://recipes.test/pulpo'])
            ->fetch();

        $this->assertSame('Pulpo á feira', $source->title);
        $this->assertSame('Edu', $source->author);

        // Several classes on one element, and a descendant under an id.
        $this->assertSame('4', $source->yield);
        $this->assertSame('Galicia', $source->source);
    }

    public function test_the_scraper_origin_needs_a_url(): void
    {
        Http::fake();

        $this->expectException(OriginException::class);
        $this->expectExceptionMessage('URL is required');

        $this->city()->source('recipe')->fetch();
    }

    public function test_a_page_that_does_not_load_is_an_origin_failure(): void
    {
        Http::fake(['recipes.test/*' => Http::response('nope', 500)]);

        $this->expectException(OriginException::class);
        $this->expectExceptionMessage('Failed to fetch HTML');

        $this->city()->source('recipe')->setVariantArguments(['url' => 'https://recipes.test/pulpo'])->fetch();
    }

    public function test_the_agent_origin_asks_anthropic_and_keeps_the_answer(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['text' => 'A flat with a view over the estuary.']],
        ])]);

        $source = $this->city()->source('copy')->setVariantArguments(['subject' => 'the flat'])->fetch();

        Http::assertSent(fn ($request) => $request->url() === 'https://api.anthropic.com/v1/messages'
            && $request->hasHeader('x-api-key', 'key-1')
            && $request['messages'][0]['content'] === 'Describe the flat');

        $this->assertSame('A flat with a view over the estuary.', $source->content);
    }

    public function test_the_agent_origin_asks_openai_when_that_is_the_provider(): void
    {
        CopywriterOrigin::$useProvider = 'openai';

        Http::fake(['api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'A flat.']]],
        ])]);

        $source = $this->city()->source('copy')->fetch();

        Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer key-1'));

        $this->assertSame('A flat.', $source->content);
    }

    public function test_the_agent_origin_says_so_when_there_is_no_api_key(): void
    {
        Config::set('larasources.origins.copywriter.api_key', null);
        Config::set('services.anthropic.key', null);

        Http::fake();

        $this->expectException(OriginException::class);
        $this->expectExceptionMessage('API key is required');

        try {
            $this->city()->source('copy')->fetch();
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_the_agent_origin_refuses_a_provider_it_does_not_know(): void
    {
        CopywriterOrigin::$useProvider = 'tarot';

        Http::fake();

        $this->expectException(OriginException::class);
        $this->expectExceptionMessage('Unsupported provider: tarot');

        $this->city()->source('copy')->fetch();
    }
}
