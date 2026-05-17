<?php

namespace App\Services\Llm;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AnthropicLlmClient implements LlmClient
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    public function __construct(
        private string $apiKey,
        private array $modelMap,        // ['classifier' => 'claude-haiku-...', 'extractor' => 'claude-sonnet-...', 'judge' => 'claude-haiku-...']
        private int $timeoutSeconds = 60,
        private int $retryAttempts = 3,
        private int $retryDelayMs = 1000,
    ) {}

    public function providerName(): string
    {
        return 'anthropic';
    }

    public function messages(
        string $modelLogicalName,
        string $systemPrompt,
        array $userContent,
        int $maxTokens = 4096,
    ): array {
        $modelId = $this->modelMap[$modelLogicalName] ?? null;
        if (! $modelId) {
            throw new RuntimeException("No Anthropic model mapped for '{$modelLogicalName}'");
        }

        $vendorContent = array_map([$this, 'convertBlock'], $userContent);

        $started = microtime(true);
        $response = $this->httpClient()->post(self::ENDPOINT, [
            'model' => $modelId,
            'max_tokens' => $maxTokens,
            'system' => $systemPrompt,
            'messages' => [['role' => 'user', 'content' => $vendorContent]],
        ]);
        $latencyMs = (int) ((microtime(true) - $started) * 1000);

        if (! $response->successful()) {
            Log::warning('llm.anthropic.request_failed', [
                'logical' => $modelLogicalName,
                'model' => $modelId,
                'http_status' => $response->status(),
                'latency_ms' => $latencyMs,
                'body_snippet' => substr($response->body(), 0, 300),
            ]);
            throw new RuntimeException(sprintf(
                'Anthropic API error %d: %s',
                $response->status(),
                substr($response->body(), 0, 500),
            ));
        }

        $body = $response->json();
        Log::info('llm.anthropic.request_ok', [
            'logical' => $modelLogicalName,
            'model' => $modelId,
            'latency_ms' => $latencyMs,
            'input_tokens' => $body['usage']['input_tokens'] ?? 0,
            'output_tokens' => $body['usage']['output_tokens'] ?? 0,
        ]);
        $text = '';
        foreach ($body['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            }
        }

        return [
            'content' => $text,
            'latency_ms' => $latencyMs,
            'input_tokens' => $body['usage']['input_tokens'] ?? 0,
            'output_tokens' => $body['usage']['output_tokens'] ?? 0,
            'model' => $body['model'] ?? $modelId,
        ];
    }

    private function convertBlock(array $block): array
    {
        return match ($block['type']) {
            'text' => ['type' => 'text', 'text' => $block['text']],
            'image' => [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'media_type' => $block['mime'],
                    'data' => $block['data_base64'],
                ],
            ],
            'pdf' => [
                'type' => 'document',
                'source' => [
                    'type' => 'base64',
                    'media_type' => 'application/pdf',
                    'data' => $block['data_base64'],
                ],
            ],
            default => throw new RuntimeException("Unknown block type: {$block['type']}"),
        };
    }

    private function httpClient(): PendingRequest
    {
        return Http::timeout($this->timeoutSeconds)
            ->retry($this->retryAttempts, $this->retryDelayMs, fn () => true, throw: false)
            ->withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => self::API_VERSION,
                'content-type' => 'application/json',
            ]);
    }
}
