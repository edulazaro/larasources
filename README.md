![Larasources](art/banner.png)

# Larasources - External data sources for Laravel models

<p align="center">
    <a href="https://github.com/edulazaro/larasources/actions/workflows/tests.yml"><img src="https://github.com/edulazaro/larasources/actions/workflows/tests.yml/badge.svg" alt="Tests"></a>
    <a href="https://packagist.org/packages/edulazaro/larasources"><img src="https://img.shields.io/packagist/v/edulazaro/larasources" alt="Latest Stable Version"></a>
    <a href="https://packagist.org/packages/edulazaro/larasources"><img src="https://img.shields.io/packagist/dt/edulazaro/larasources" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/edulazaro/larasources"><img src="https://img.shields.io/packagist/php-v/edulazaro/larasources" alt="PHP Version"></a>
    <a href="https://github.com/edulazaro/larasources/blob/main/LICENSE"><img src="https://img.shields.io/packagist/l/edulazaro/larasources" alt="License"></a>
</p>

A Laravel package for integrating external data sources into your models, with a write-through cache in your own database. Work with external APIs using model-like abstractions, without forcing those APIs to live in your own tables.

## Why

Larasources lets your Eloquent models pull and push data from external services through a typed, declarative `Source` API. Each source declares its fillable fields, casts, mappings, and origin (the API client). Your domain model stays clean, the integration layer stays separated, and the cached external state lives in a single dedicated table.

## Features

- **Model-like Sources**: define external resources as classes with `fillable`, `casts`, accessors, and arguments
- **Origins**: pluggable API clients (`fetch`, `save`, `delete`) decoupled from the data shape
- **Outcomes the origin reports**: a write is stored only when the origin took it, and a service that has not finished yet says so
- **Built-in caching** through the `sources` table (`SourceRecord`)
- **Variants and arguments** to handle multiple operations or per-call parameters
- **No package configuration**: credentials, timeout and retries belong to each origin
- **Mockable** for tests via `mockSource()`

## Requirements

- PHP `>=8.4` (any future version included)
- Laravel `>=12.0` (any future version included)

Continuous integration runs the suite against every supported PHP and Laravel combination,
plus PHP nightly as an early warning.

## Installation

```bash
composer require edulazaro/larasources
```

Run the migrations:

```bash
php artisan migrate
```

## Configuration

The package has no configuration file and no environment variables of its own. What an
origin needs is the integration's own, and `config()` is where it resolves it.

`config()` works like Laravel's `config()` helper without the prefix: the origin puts
`larasources.origins.{alias}` in front and the caller only names the key, so static
credentials can live in a config of your own:

```php
// config/larasources.php, if you want this convention
'origins' => [
    'my_provider' => [
        'api_key' => env('MY_PROVIDER_API_KEY'),
        'sandbox' => env('MY_PROVIDER_SANDBOX', false),
        'timeout' => 15,
        'retry' => ['attempts' => 3, 'delay' => 1000],
    ],
],
```

When the credentials are not static, which is the usual case in multi-tenant applications,
override `config()` and read them from wherever they live:

```php
class MyProviderOrigin extends Origin
{
    protected function config(?string $key = null, mixed $default = null): mixed
    {
        $credentials = $this->integration->credentials ?? [];

        return $key ? ($credentials[$key] ?? $default) : $credentials;
    }
}
```

`timeout` and `retry` go through the same method, so an origin that builds its requests
with `$this->http()` gets the timeout and the retries of that integration. Without them the
client makes a single attempt with the origin's `$timeout` property.

A Source has the same accessor over its own block, `larasources.sources.{name}`, where the
name is the key it is mapped under on the model:

```php
'sources' => [
    'weather' => ['units' => 'metric'],
],
```

```php
$this->config('units');     // inside the source
$source->config('units');   // from outside
```

Override it the same way when those settings are not static, for instance to serve them
from the origin's integration.

## Usage

### 1. Add the `HasSources` trait to your model

```php
use Illuminate\Database\Eloquent\Model;
use EduLazaro\Larasources\Concerns\HasSources;
use App\Sources\WeatherSource;

class City extends Model
{
    use HasSources;

    protected array $sources = [
        'weather' => WeatherSource::class,
    ];
}
```

### 2. Read and write through the source

```php
$city = City::find(1);

// Access external data (autoloaded from cache or fetched on miss)
$weather = $city->source('weather');
echo $weather->temperature;
echo $weather->humidity;

// Push data to the external API and persist locally. Failures throw.
$city->source('weather')->save();

// Same, reporting the outcome instead of throwing
$result = $city->source('weather')->trySave();

if ($result->failed()) {
    return back()->withErrors($result->message);
}

// Read live from the API and refresh the cached record
$fresh = $city->source('weather')->fetch();

// Delete remote and clear cache
$city->source('weather')->delete();
```

### 3. Define a Source

```php
namespace App\Sources;

use EduLazaro\Larasources\Source;
use EduLazaro\Larasources\Attributes\UsesOrigin;
use App\Origins\MyProviderOrigin;

#[UsesOrigin(MyProviderOrigin::class)]
class WeatherSource extends Source
{
    protected $fillable = [
        'temperature',
        'humidity',
        'description',
    ];

    protected $casts = [
        'temperature' => 'float',
        'humidity'    => 'integer',
    ];

    protected function arguments(): array
    {
        return [
            'city_id' => 'external_id', // maps to $city->external_id
        ];
    }

    public function getFeelsLikeAttribute(): float
    {
        return $this->temperature - ($this->humidity / 10);
    }
}
```

### 4. Define an Origin (the API client)

```php
namespace App\Origins;

use EduLazaro\Larasources\Origins\Origin;
use Illuminate\Support\Facades\Http;

class MyProviderOrigin extends Origin
{
    public static function getAlias(): string
    {
        return 'my_provider';
    }

    public function fetch(array $arguments = []): array
    {
        $response = Http::withToken($this->config('api_key'))
            ->get('https://api.example.com/weather/' . $arguments['city_id']);

        return $response->json();
    }

    public function save(array $data): array
    {
        $response = Http::withToken($this->config('api_key'))
            ->post('https://api.example.com/weather', $data);

        return $response->json();
    }

    public function delete(): bool
    {
        return true;
    }
}
```

### 5. Variants and arguments

Use variants to handle multiple modes per source (for example, `sale` vs `rent` for a property listing, or `current` vs `forecast` for weather):

```php
$city->source('weather')->setVariant('forecast')->fetch();

// Pass runtime arguments
$city->source('weather', ['city_id' => 'custom_id'])->fetch();
```

The `arguments()` map declares which attribute of the model feeds each argument, and
`resolveArguments()` turns it into values: with `['city_id' => 'external_id']` the origin
receives `['city_id' => $city->external_id]`, or `null` when the attribute is empty.
Precedence, from lowest to highest: the `$arguments` property, the `arguments()` method,
the arguments stored on the `SourceRecord`, and the runtime arguments passed to `source()`.

Arguments that do not come from the model live on the record, either in its `arguments`
JSON column or as `SourceArgument` rows pointing at another model or record. They are
written by your app, not by `persist()`, which only refreshes the cached attributes: a
resolved value stored there would shadow the model from then on.

## Caching

Sources are cached automatically in the `sources` table (the `SourceRecord` model). Each
record is keyed by `(sourceable, name, variant)`.

Reading an attribute autoloads: it fills from the cached record when there is one, and goes
to the origin when there is not. When the origin cannot answer that first read, the source
falls back to `build()` in memory and **nothing is stored**: a record written there would
say the origin has data it has never seen, and every later read would trust it. The next
read goes back to the origin. Only `OriginException` is caught, so a bug in an origin
surfaces instead of looking like a service being down. `fetch()` always goes to the origin and **writes the
result through to the record**, so the cache is never left behind after a live read. A
source with no model attached has nothing to cache, and `fetch()` just fills the instance.

```php
// Has it ever been fetched/saved? One query, looked up once per instance
if ($source->record()) {
    // It is stored locally
}

// What the origin says right now, cached on the way out
$source->fetch();

// Compare what is stored with what the origin has
$stored = $source->record()->toArray();   // name, variant, origin, attributes, dates
$live   = $source->fetch()->toArray();    // the fields, straight from the service

// Clear the cache for this source
$source->clear();
```

The cache does not expire on its own: once a record exists, reading attributes never calls
the origin again. Refresh it when your app decides to, with `fetch()`.

### Records the origin has not finished with

Some services take the payload and publish it later. An origin says so by returning an
`OriginResult` from `save()` instead of the response array:

```php
use EduLazaro\Larasources\Enums\OriginStatus;
use EduLazaro\Larasources\OriginResult;

public function save(array $data): array|OriginResult
{
    $response = $this->http()->post($this->endpoint, $data)->throw()->json();

    return new OriginResult(
        status: $response['published'] ? OriginStatus::Saved : OriginStatus::Processing,
        data: $response,
        message: $response['reason'] ?? null,
    );
}
```

The status is stored on the record, so a `processing` row says "the service took this and
had not finished with it". Reading it never calls the origin: nothing in this package goes
to a service while you read an attribute of a record that exists.

What is still waiting is a query, not something your app has to remember:

```php
SourceRecord::processing()->where('updated_at', '<', now()->subHour())->get();
```

And `reconcile()` resolves one. It re-reads from the origin when the record is still
processing and leaves it alone when it is not, which is the only thing that turns it into
`saved`. It converges on whatever the service really ended up with, even when the payload
was rejected in the end:

```php
// Where the latency is yours to spend: a scheduled command, not a page render
foreach (SourceRecord::processing()->get() as $record) {
    try {
        $record->toSource(ListingSource::class)->reconcile();
    } catch (OriginException $e) {
        // One service being down does not stop the rest
    }
}

// Or on the source you already have
$listing = $property->source('listing')->reconcile();
```

A record only exists because an origin took the data, so `status` holds `saved` or
`processing`, never `failed`.

## Error handling

An origin signals failure by throwing: returning means it took the data, and the source
persists its record right after the call returns. So a failure that returns instead of
throwing is stored as if it had worked.

```php
use EduLazaro\Larasources\Exceptions\OriginException;

try {
    $weather = $city->source('weather')->fetch();
} catch (OriginException $e) {
    Log::error('Provider error: ' . $e->getMessage());
}
```

That is what a queued job wants: it catches, backs off and retries. When the caller would
rather branch than catch, typically because it renders, `trySave()` returns the outcome and
persists nothing unless the origin took the data:

```php
$result = $city->source('weather')->trySave();

match ($result->status) {
    OriginStatus::Saved      => $this->notify('Published'),
    OriginStatus::Processing => $this->notify('Publishing'),
    OriginStatus::Failed     => $this->notify($result->message),
};
```

Nothing is written when a save fails, so what was already stored stays untouched: the
record never gets ahead of the origin.

## Testing

Mock a source so it returns a fixed instance instead of hitting the origin:

```php
$mock = new WeatherSource(['temperature' => 22.5, 'humidity' => 60]);
$city->mockSource(WeatherSource::class, $mock);

$weather = $city->source('weather');
// $weather is the mocked instance
```

## API reference

### Source

- `fetch()`: read live from the origin and refresh the cached record
- `save()`: push current attributes to the origin and persist. Throws when the origin does not take them
- `trySave()`: the same, returning an `OriginResult` instead of throwing
- `reconcile()`: re-read from the origin when the record is still `processing`, a no-op when it is not
- `persist(?OriginStatus $status)`: write the record for this source
- `delete()`: delete remote and clear cache. The record stays when the origin throws, and also when it reports it did not delete anything
- `clear()`: clear cached record only
- `origin()`: get the resolved Origin instance
- `record()`, `getRecord()`: the stored `SourceRecord`, or `null`. Looked up in the table the first time it is asked for, never from the origin
- `setVariant(string $variant)`: set the source's variant
- `setVariantArguments(array $args)`: pass runtime arguments
- `config(?string $key, mixed $default)`: this source's settings, under `larasources.sources.{name}`
- `name()`: the key this source is mapped under on the model

### Origin

- `fetch(array $arguments): array`
- `save(array $data): array|OriginResult`: return the response, or an `OriginResult` to report a status. Throw `OriginException` when the service did not take the data
- `delete(): bool`
- `regenerate(): array`
- `getAlias(): string`
- `config(?string $key, mixed $default)`: resolve this integration's settings, under `larasources.origins.{alias}`. Override it when they are not static
- `http()`: HTTP client with the integration's `timeout` and `retry`

### SourceRecord

- `processing()`: scope over the records the origin had not finished with
- `toSource(string $sourceClass)`: turn the stored record back into a source

### OriginResult

What the origin reports about a write. `status` is an `OriginStatus` (`Saved`,
`Processing`, `Failed`), `data` the response, `message` the reason when there is one and
`exception` the cause of a failure.

- `ok()`: the origin took the data, stored or being processed
- `processing()`: the service has not finished with it
- `failed()`: the service did not take it

### Bundled abstract Origins

- `Origin`: base class
- `RemoteOrigin`: generic REST client base
- `AgentOrigin`: for agent-style integrations
- `ScraperOrigin`: for HTML scraping with `getHtml()` helper

## Testing

```bash
composer install
composer test
```

The suite runs on Testbench with an in-memory SQLite database. `tests/Fixtures` holds a
sourceable model, a source and two origins (one plain, one going through the package's HTTP
client) that the tests build on.

## Sponsors

Larasources is supported by the following sponsors. Thank you for keeping it growing:

<p>
  <a href="https://kenodo.com"><img src="art/logo-kenodo.png" width="24" alt="Kenodo"></a>&nbsp;<a href="https://kenodo.com">Kenodo</a>&nbsp;&nbsp;&nbsp;&nbsp;
  <a href="https://andorradev.com"><img src="art/logo-andorradev.png" width="24" alt="AndorraDev"></a>&nbsp;<a href="https://andorradev.com">AndorraDev</a>
</p>

## Author

Created by [Edu Lazaro](https://edulazaro.com)

## License

Larasources is open-sourced software licensed under the [MIT license](LICENSE).
