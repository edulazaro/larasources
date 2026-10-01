<?php

namespace EduLazaro\Larasources\Tests\Fixtures;

use EduLazaro\Larasources\Origins\Origin;
use EduLazaro\Larasources\OriginResult;
use Throwable;

class FakeWeatherOrigin extends Origin
{
    /** Arguments received by the last fetch call. */
    public static array $lastFetchArguments = [];

    /** Payload received by the last save call. */
    public static array $lastSavedPayload = [];

    /** Whether delete() was called. */
    public static bool $deleted = false;

    /** How many times fetch() was called. */
    public static int $fetchCalls = 0;

    /** When set, save() returns this instead of the payload it received. */
    public static ?OriginResult $saveResult = null;

    /** When set, save() throws it. */
    public static ?Throwable $saveException = null;

    /** When set, fetch() throws it. */
    public static ?Throwable $fetchException = null;

    /** What delete() reports. */
    public static bool $deleteResult = true;

    public static function reset(): void
    {
        static::$lastFetchArguments = [];
        static::$lastSavedPayload = [];
        static::$deleted = false;
        static::$fetchCalls = 0;
        static::$saveResult = null;
        static::$saveException = null;
        static::$fetchException = null;
        static::$deleteResult = true;
    }

    public static function getAlias(): string
    {
        return 'fake_weather';
    }

    public function fetch(array $arguments = []): array
    {
        static::$fetchCalls++;
        static::$lastFetchArguments = $arguments;

        if (static::$fetchException) {
            throw static::$fetchException;
        }

        return ['temperature' => 21.5];
    }

    public function save(array $data): array|OriginResult
    {
        static::$lastSavedPayload = $data;

        if (static::$saveException) {
            throw static::$saveException;
        }

        return static::$saveResult ?? $data;
    }

    public function delete(): bool
    {
        static::$deleted = true;

        return static::$deleteResult;
    }
}
