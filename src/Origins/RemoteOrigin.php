<?php

namespace EduLazaro\Larasources\Origins;

use EduLazaro\Larasources\Exceptions\OriginException;
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

    public function save(array $data): array
    {
        $this->ensureConfigured();

        $id = $data['id'] ?? null;
        $method = $id ? 'put' : 'post';
        $endpoint = $id ? $this->endpoint($data) : $this->storeEndpoint($data);

        try {
            $response = $this->buildRequest()
                ->$method($this->baseUrl . $endpoint, $this->transformForSave($data));

            $response->throw();

            return $this->transformResponse($response->json());
        } catch (RequestException $e) {
            throw new OriginException("Save failed: " . $e->getMessage());
        }
    }

    public function delete(): bool
    {
        $this->ensureConfigured();

        $arguments = $this->source->resolveArguments();

        try {
            $response = $this->buildRequest()
                ->delete($this->baseUrl . $this->endpoint($arguments));

            return $response->successful();
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

    protected function ensureConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new OriginException(static::class . ' is not properly configured');
        }
    }
}
