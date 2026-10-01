<?php

namespace EduLazaro\Larasources\Tests\Fixtures;

use EduLazaro\Larasources\Concerns\HasSources;
use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    use HasSources;

    protected $table = 'cities';

    protected $guarded = [];

    protected array $sources = [
        'weather' => WeatherSource::class,
        'listing' => ListingSource::class,
        'recipe' => RecipeSource::class,
        'copy' => CopySource::class,
    ];
}
