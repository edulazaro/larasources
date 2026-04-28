<?php

namespace EduLazaro\Larasources\Origins;

use EduLazaro\Larasources\Exceptions\OriginException;
use Illuminate\Http\Client\RequestException;

abstract class AgentOrigin extends Origin
{
    protected string $provider = 'anthropic';

    protected string $model = 'claude-sonnet-4-5-20250514';

    protected float $temperature = 0.7;

    protected int $maxTokens = 1024;

    public function fetch(array $arguments = []): array
    {
        $prompt = $this->prompt($arguments);

        if (!$prompt) {
            throw new OriginException('Prompt is required');
        }

        try {
            $content = match ($this->provider) {
                'anthropic' => $this->callAnthropic($prompt),
                'openai' => $this->callOpenAI($prompt),
                default => throw new OriginException("Unsupported provider: {$this->provider}"),
            };

            return $this->parseResponse($content);
        } catch (RequestException $e) {
            throw new OriginException("Agent request failed: " . $e->getMessage());
        }
    }

    abstract protected function prompt(array $arguments): string;

    protected function parseResponse(string $content): array
    {
        return ['content' => $content];
    }

    protected function systemPrompt(): ?string
    {
        return null;
    }

    protected function callAnthropic(string $prompt): string
    {
        $apiKey = $this->getConfig('api_key') ?? config('services.anthropic.key');

        if (!$apiKey) {
            throw new OriginException('Anthropic API key is required');
        }

        $body = [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ];

        if ($this->systemPrompt()) {
            $body['system'] = $this->systemPrompt();
        }

        $response = $this->http()
            ->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ])
            ->post('https://api.anthropic.com/v1/messages', $body);

        $response->throw();

        return $response->json('content.0.text');
    }

    protected function callOpenAI(string $prompt): string
    {
        $apiKey = $this->getConfig('api_key') ?? config('services.openai.key');

        if (!$apiKey) {
            throw new OriginException('OpenAI API key is required');
        }

        $messages = [];

        if ($this->systemPrompt()) {
            $messages[] = ['role' => 'system', 'content' => $this->systemPrompt()];
        }

        $messages[] = ['role' => 'user', 'content' => $prompt];

        $response = $this->http()
            ->withToken($apiKey)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'max_tokens' => $this->maxTokens,
                'temperature' => $this->temperature,
                'messages' => $messages,
            ]);

        $response->throw();

        return $response->json('choices.0.message.content');
    }

    protected static function getConfigKey(): string
    {
        return 'agent';
    }
}
