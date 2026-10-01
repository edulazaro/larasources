<?php

namespace EduLazaro\Larasources\Tests\Fixtures;

use EduLazaro\Larasources\Attributes\UsesOrigin;

/**
 * A source that re-checks a processing record on the very next read, which is
 * how a service that answers immediately would be configured.
 */
#[UsesOrigin(FakeWeatherOrigin::class)]
class EagerWeatherSource extends WeatherSource
{
    protected int $checkProcessingAfter = 0;
}
