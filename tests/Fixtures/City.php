<?php

namespace EduLazaro\Larasources\Tests\Fixtures;

use EduLazaro\Larasources\Concerns\HasSources;
use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    use HasSources;

    protected $table = 'cities';

    protected $guarded = [];

    /**
     * The map is filled in the constructor because the trait already declares
     * the property and PHP rejects a redeclaration with a different default.
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->sources = [
            'weather' => WeatherSource::class,
        ];
    }
}
