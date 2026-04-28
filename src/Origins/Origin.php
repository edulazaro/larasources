<?php

namespace EduLazaro\Larasources\Origins;

use EduLazaro\Larasources\Source;
use EduLazaro\Larasources\Exceptions\OriginException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

abstract class Origin
{
    protected Source $source;

    protected int $timeout = 30;

    public function __construct(Source $source)
    {
        $this->source = $source;
    }

    public function getSource(): Source
    {
        return $this->source;
    }

    protected function http(): PendingRequest
    {
        $request = Http::timeout($this->timeout);

        $retry = config('larasources.retry', []);
        if (!empty($retry['enabled'])) {
            $request->retry(
                $retry['max_attempts'] ?? 3,
                $retry['delay'] ?? 1000
            );
        }

        return $request;
    }

    abstract public function fetch(array $arguments = []): array;

    public function save(array $data): array
    {
        throw new OriginException('Save is not supported by ' . static::class);
    }

    public function delete(): bool
    {
        throw new OriginException('Delete is not supported by ' . static::class);
    }

    public function regenerate(): array
    {
        return $this->fetch($this->source->resolveArguments());
    }

    protected function getConfig(string $key = null, mixed $default = null): mixed
    {
        $configKey = 'larasources.origins.' . static::getAlias();

        if ($key) {
            $configKey .= '.' . $key;
        }

        return config($configKey, $default);
    }

    abstract public static function getAlias(): string;

    public function isConfigured(): bool
    {
        return !empty($this->getConfig());
    }
}