<?php

namespace App\Services\Llm;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GeminiLlmClient implements LlmClient
{
    private const BASE = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct(
        private string $apiKey,
        private array $modelMap,        // ['classifier' => 'gemini-2.5-flash', 'extractor' => 'gemini-2.5-pro', 'judge' => 'gemini-2.5-flash']
        private int $timeoutSeconds = 90,
        private int $retryAttempts = 3,
        private int $retryDelayMs = 1000,
    ) {}

    public function providerName(): string
    {
        return 'gemini';
    }

    public function messages(
        string $modelLogicalName,
        string $systemPrompt,
        array $userContent,
        int $maxTokens = 4096,
    ): array {
        $modelId = $this->modelMap[$modelLogicalName] ?? null;
        if (! $modelId) {
            throw new RuntimeException("No Gemini model mapped for '{$modelLogicalName}'");
        }

        $parts = array_map([$this, 'convertBlock'], $userContent);

        // Gemini 2.5 series uses internal "thinking" tokens that eat the output budget.
        // For deterministic extraction we disable thinking entirely (thinkingBudget = 0)
        // and bump the visible output budget so the JSON always fits.
        $payload = [
            'system_instruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents' => [[
                'role' => 'user',
                'parts' => $parts,
            ]],
            'generationConfig' => [
                'maxOutputTokens' => max($maxTokens, 2048),
                'temperature' => 0.1,
                'responseMimeType' => 'application/json',
                'thinkingConfig' => [
                    'thinkingBudget' => 0,
                ],
            ],
        ];

        $url = sprintf('%s/models/%s:generateContent', self::BASE, $modelId);

        $started = microtime(true);
        $response = $this->httpClient()->post($url, $payload);
        $latencyMs = (int) ((microtime(true) - $started) * 1000);

        if (! $response->successful()) {
            Log::warning('llm.gemini.request_failed', [
                'logical' => $modelLogicalName,
                'model' => $modelId,
                'http_status' => $response->status(),
                'latency_ms' => $latencyMs,
                'body_snippet' => substr($response->body(), 0, 300),
            ]);
            throw new RuntimeException(sprintf(
                'Gemini API error %d: %s',
                $response->status(),
                substr($response->body(), 0, 500),
            ));
        }

        $body = $response->json();
        $finishReason = $body['candidates'][0]['finishReason'] ?? null;
        $outputTokens = $body['usageMetadata']['candidatesTokenCount'] ?? 0;
        $thinkingTokens = $body['usageMetadata']['thoughtsTokenCount'] ?? 0;

        Log::info('llm.gemini.request_ok', [
            'logical' => $modelLogicalName,
            'model' => $modelId,
            'latency_ms' => $latencyMs,
            'input_tokens' => $body['usageMetadata']['promptTokenCount'] ?? 0,
            'output_tokens' => $outputTokens,
            'thinking_tokens' => $thinkingTokens,
            'finish_reason' => $finishReason,
        ]);

        if (in_array($finishReason, ['MAX_TOKENS', 'SAFETY', 'RECITATION', 'PROHIBITED_CONTENT'], true)) {
            Log::warning('llm.gemini.truncated_or_blocked', [
                'logical' => $modelLogicalName,
                'finish_reason' => $finishReason,
                'output_tokens' => $outputTokens,
                'thinking_tokens' => $thinkingTokens,
                'response_snippet' => substr(json_encode($body), 0, 500),
            ]);
        }
        $text = '';
        $candidates = $body['candidates'] ?? [];
        if (! empty($candidates[0]['content']['parts'])) {
            foreach ($candidates[0]['content']['parts'] as $part) {
                if (isset($part['text'])) {
                    $text .= $part['text'];
                }
            }
        }

        return [
            'content' => $text,
            'latency_ms' => $latencyMs,
            'input_tokens' => $body['usageMetadata']['promptTokenCount'] ?? 0,
            'output_tokens' => $body['usageMetadata']['candidatesTokenCount'] ?? 0,
            'model' => $modelId,
        ];
    }

    private function convertBlock(array $block): array
    {
        return match ($block['type']) {
            'text' => ['text' => $block['text']],
            'image', 'pdf' => [
                'inline_data' => [
                    'mime_type' => $block['mime'],
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
                'x-goog-api-key' => $this->apiKey,
                'content-type' => 'application/json',
            ]);
    }
}
