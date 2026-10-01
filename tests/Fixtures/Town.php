<?php

namespace EduLazaro\Larasources\Tests\Fixtures;

use EduLazaro\Larasources\Concerns\HasSources;
use Illuminate\Database\Eloquent\Model;

/**
 * Declares its sources with the method, the form a model cannot express with
 * the trait's property.
 */
class Town extends Model
{
    use HasSources;

    protected $table = 'cities';

    protected $guarded = [];

    protected function sources(): array
    {
        return [
            'weather' => WeatherSource::class,
        ];
    }
}
