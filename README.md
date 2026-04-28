# Larasources

A Laravel package for integrating external data sources into your models with caching, retry, and rate-limiting built in. Work with external APIs using model-like abstractions, without forcing those APIs to live in your own database tables.

## Why

Larasources lets your Eloquent models pull and push data from external services through a typed, declarative `Source` API. Each source declares its fillable fields, casts, mappings, and origin (the API client). Your domain model stays clean, the integration layer stays separated, and the cached external state lives in a single dedicated table.

## Features

- **Model-like Sources**: define external resources as classes with `fillable`, `casts`, accessors, and arguments
- **Origins**: pluggable API clients (`fetch`, `save`, `delete`) decoupled from the data shape
- **Built-in caching** through the `sources` table (`SourceRecord`)
- **Variants and arguments** to handle multiple operations or per-call parameters
- **Retry and rate-limiting** declared in config, applied automatically
- **Mockable** for tests via `mockSource()`

## Requirements

- PHP `>=8.4` (any future version included)
- Laravel `>=9.0` (any future version included)

## Installation

```bash
composer require edulazaro/larasources
```

Publish the configuration and migrations:

```bash
php artisan vendor:publish --provider="EduLazaro\Larasources\LarasourcesServiceProvider"
php artisan migrate
```

## Configuration

Origin-specific credentials live in `config/larasources.php` under `origins`, keyed by your origin's alias:

```php
'origins' => [
    'my_provider' => [
        'api_key' => env('MY_PROVIDER_API_KEY'),
        'sandbox' => env('MY_PROVIDER_SANDBOX', false),
    ],
],
```

Then in `.env`:

```env
MY_PROVIDER_API_KEY=your_api_key
MY_PROVIDER_SANDBOX=true
```

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

// Push data to the external API and persist locally
$city->source('weather')->save();

// Force refresh from the API (bypasses cache)
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
        $response = Http::withToken($this->getConfig('api_key'))
            ->get('https://api.example.com/weather/' . $arguments['city_id']);

        return $response->json();
    }

    public function save(array $data): array
    {
        $response = Http::withToken($this->getConfig('api_key'))
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

## Caching

Sources are cached automatically in the `sources` table (the `SourceRecord` model). Each record is keyed by `(sourceable, name, variant)`.

```php
// Has it ever been fetched/saved?
if ($source->getRecord()) {
    // Data is cached locally
}

// Clear the cache for this source
$source->clear();
```

## Error handling

```php
use EduLazaro\Larasources\Exceptions\OriginException;

try {
    $weather = $city->source('weather')->fetch();
} catch (OriginException $e) {
    Log::error('Provider error: ' . $e->getMessage());
}
```

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

- `fetch()`: pull fresh data from the origin
- `save()`: push current attributes to the origin and persist
- `saveToOrigin()`: push without persisting locally
- `delete()`: delete remote and clear cache
- `clear()`: clear cached record only
- `origin()`: get the resolved Origin instance
- `getRecord()`: get the underlying `SourceRecord` (or `null`)
- `setVariant(string $variant)`: set the source's variant
- `setVariantArguments(array $args)`: pass runtime arguments

### Origin

- `fetch(array $arguments): array`
- `save(array $data): array`
- `delete(): bool`
- `regenerate(): array`
- `getAlias(): string`

### Bundled abstract Origins

- `Origin`: base class
- `RemoteOrigin`: generic REST client base
- `AgentOrigin`: for agent-style integrations
- `ScraperOrigin`: for HTML scraping with `getHtml()` helper

## Credits

Developed by [Edu Lázaro](https://edulazaro.com).

## License

MIT
