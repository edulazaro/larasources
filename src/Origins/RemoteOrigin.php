<?php

namespace EduLazaro\Larasources\Origins;

use EduLazaro\Larasources\Enums\OriginStatus;
use EduLazaro\Larasources\Exceptions\OriginException;
use EduLazaro\Larasources\OriginResult;
use Illuminate\Http\Client\RequestException;

abstract class RemoteOrigin extends Origin
{
    protected string $baseUrl = '';

    protected string $authType = 'none';

    protected string $authHeader = 'Authorization';

    protected array $defaultHeaders = [
        'Accept' => 'application/json',
    ];

    public function fetch(array $arguments = []): array
    {
        $this->ensureConfigured();

        try {
            $response = $this->buildRequest()
                ->get($this->baseUrl . $this->endpoint($arguments));

            $response->throw();

            return $this->transformResponse($response->json());
        } catch (RequestException $e) {
            throw new OriginException("Fetch failed: " . $e->getMessage());
        }
    }

    /**
     * Create or update, depending on whether the resource already exists.
     *
     * The stored external id decides it when there is one, which is optional:
     * an application that keeps the id in its own model, or feeds it through
     * the payload, works exactly as before.
     */
    public function save(array $data): array|OriginResult
    {
        $this->ensureConfigured();

        $id = $this->source->externalId() ?? $data['id'] ?? null;

        $method = $id ? 'put' : 'post';
        $endpoint = $id ? $this->endpoint($data + ['id' => $id]) : $this->storeEndpoint($data);

        try {
            $response = $this->buildRequest()
                ->$method($this->baseUrl . $endpoint, $this->transformForSave($data));

            $response->throw();

            $body = $this->transformResponse($response->json() ?? []);

            return new OriginResult(
                status: OriginStatus::Saved,
                data: $body,
                externalId: $this->externalIdFrom($body) ?? $id,
            );
        } catch (RequestException $e) {
            throw new OriginException("Save failed: " . $e->getMessage());
        }
    }

    /**
     * The id the service gave the resource, read from its response.
     *
     * A REST service usually calls it `id`. Override this when yours does not,
     * or return null to keep whatever was already stored.
     */
    protected function externalIdFrom(array $response): ?string
    {
        return isset($response['id']) ? (string) $response['id'] : null;
    }

    /**
     * Delete the resource.
     *
     * Returning means it is gone, which is also true when the service says it
     * never had it: deleting twice is not a failure. Anything else throws, so
     * the source keeps its record instead of claiming the resource is gone.
     */
    public function delete(): bool
    {
        $this->ensureConfigured();

        $arguments = $this->source->resolveArguments();

        try {
            $response = $this->buildRequest()
                ->delete($this->baseUrl . $this->endpoint($arguments));

            if ($response->notFound()) {
                return true;
            }

            $response->throw();

            return true;
        } catch (RequestException $e) {
            throw new OriginException("Delete failed: " . $e->getMessage());
        }
    }

    protected function buildRequest()
    {
        $request = $this->http()->withHeaders($this->defaultHeaders);

        return match ($this->authType) {
            'bearer' => $request->withToken($this->config('api_token')),
            'basic' => $request->withBasicAuth($this->config('username'), $this->config('password')),
            'key' => $request->withHeaders([$this->authHeader => $this->config('api_key')]),
            default => $request,
        };
    }

    abstract protected function endpoint(array $arguments): string;

    protected function storeEndpoint(array $data): string
    {
        return $this->endpoint($data);
    }

    protected function transformResponse(array $data): array
    {
        return $data;
    }

    protected function transformForSave(array $data): array
    {
        return $data;
    }

    /**
     * Whether this origin has what it is about to use.
     *
     * What it needs is a base url and the credentials its `authType` reads, not
     * the existence of a config block: `config()` can be overridden to serve
     * them from the database, which is where an integration's credentials
     * usually live.
     */
    public function isConfigured(): bool
    {
        if ($this->baseUrl === '') {
            return false;
        }

        return match ($this->authType) {
            'bearer' => (bool) $this->config('api_token'),
            'basic' => $this->config('username') && $this->config('password'),
            'key' => (bool) $this->config('api_key'),
            default => true,
        };
    }

    protected function ensureConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new OriginException(static::class . ' is not properly configured');
        }
    }
}
