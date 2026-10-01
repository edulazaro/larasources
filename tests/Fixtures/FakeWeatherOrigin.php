<?php

namespace EduLazaro\Larasources\Tests\Fixtures;

use EduLazaro\Larasources\Origins\Origin;

class FakeWeatherOrigin extends Origin
{
    /** Arguments received by the last fetch call. */
    public static array $lastFetchArguments = [];

    public static function getAlias(): string
    {
        return 'fake_weather';
    }

    public function fetch(array $arguments = []): array
    {
        static::$lastFetchArguments = $arguments;

        return ['temperature' => 21.5];
    }

    /** Payload received by the last save call. */
    public static array $lastSavedPayload = [];

    /** Whether delete() was called. */
    public static bool $deleted = false;

    public function save(array $data): array
    {
        static::$lastSavedPayload = $data;

        return $data;
    }

    public function delete(): bool
    {
        static::$deleted = true;

        return true;
    }
}
