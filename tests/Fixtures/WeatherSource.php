<?php

namespace EduLazaro\Larasources\Tests\Fixtures;

use EduLazaro\Larasources\Attributes\UsesOrigin;
use EduLazaro\Larasources\Source;

#[UsesOrigin(FakeWeatherOrigin::class)]
class WeatherSource extends Source
{
    protected $fillable = ['temperature'];

    protected $casts = ['temperature' => 'float'];

    /**
     * Lowest precedence map, declared as a property.
     */
    protected array $arguments = [
        'region' => 'region_code',
    ];

    protected function arguments(): array
    {
        return [
            'city_id' => 'external_id',
        ];
    }

    public function build(): void
    {
        $this->fill(['temperature' => 18.0]);
    }
}
