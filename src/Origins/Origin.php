<?php

namespace EduLazaro\Larasources\Origins;

use EduLazaro\Larasources\Source;
use EduLazaro\Larasources\OriginResult;
use EduLazaro\Larasources\Exceptions\OriginException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Config;
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

    /**
     * HTTP client for this origin.
     *
     * Timeout and retries belong to the integration, not to the package, so
     * they are read through `config()`: an origin can serve them from its
     * own config block, from the database or from wherever its credentials
     * live. Without them the client makes a single attempt.
     *
     *   'retry' => ['attempts' => 3, 'delay' => 1000]   // delay in ms
     */
    protected function http(): PendingRequest
    {
        $request = Http::timeout((int) ($this->config('timeout') ?? $this->timeout));

        $retry = $this->config('retry') ?? [];
        $attempts = (int) ($retry['attempts'] ?? 1);

        if ($attempts > 1) {
            $request->retry($attempts, (int) ($retry['delay'] ?? 1000));
        }

        return $request;
    }

    abstract public function fetch(array $arguments = []): array;

    /**
     * Write this source's data to the origin.
     *
     * Returning means the origin took the data: the source persists its record
     * right after this call returns. So when the service refuses the data,
     * throw an `OriginException`. A failure that returns instead of throwing
     * is stored as if it had worked, and no caller can tell the difference.
     *
     * Return the response array for the usual case, or an `OriginResult` when
     * the service says something an array cannot, typically that it took the
     * payload and has not finished with it yet (`OriginStatus::Processing`).
     */
    public function save(array $data): array|OriginResult
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

    /**
     * This integration's settings.
     *
     * Works like Laravel's `config()` helper without the prefix: the origin
     * puts `larasources.origins.{alias}` in front, so the caller only names the
     * key, and without a key it returns the whole block. Override it when the
     * settings are not static, for example per tenant in the database.
     */
    protected function config(?string $key = null, mixed $default = null): mixed
    {
        $configKey = 'larasources.origins.' . static::getAlias();

        if ($key) {
            $configKey .= '.' . $key;
        }

        return Config::get($configKey, $default);
    }

    /**
     * @deprecated Use config(). Overriding this no longer changes what the
     *             origin reads: the package calls config().
     */
    protected function getConfig(?string $key = null, mixed $default = null): mixed
    {
        return $this->config($key, $default);
    }

    abstract public static function getAlias(): string;

    public function isConfigured(): bool
    {
        return !empty($this->config());
    }
}