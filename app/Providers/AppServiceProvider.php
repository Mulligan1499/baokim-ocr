<?php

namespace App\Providers;

use App\Services\Llm\AnthropicLlmClient;
use App\Services\Llm\GeminiLlmClient;
use App\Services\Llm\LlmClient;
use App\Services\Ocr\Stage5ConfidenceAggregator;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LlmClient::class, function () {
            $provider = config('ocr.llm_provider', 'gemini');

            return match ($provider) {
                'anthropic' => $this->makeAnthropic(),
                'gemini' => $this->makeGemini(),
                default => throw new RuntimeException("Unknown LLM provider: {$provider}"),
            };
        });

        $this->app->singleton(Stage5ConfidenceAggregator::class, function () {
            return Stage5ConfidenceAggregator::fromConfig();
        });
    }

    public function boot(): void
    {
        //
    }

    private function makeAnthropic(): LlmClient
    {
        $cfg = config('ocr.anthropic');
        if (empty($cfg['api_key'])) {
            throw new RuntimeException('ANTHROPIC_API_KEY not set');
        }
        return new AnthropicLlmClient(
            apiKey: $cfg['api_key'],
            modelMap: [
                'classifier' => $cfg['model_classifier'],
                'extractor' => $cfg['model_extractor'],
                'judge' => $cfg['model_judge'],
            ],
            timeoutSeconds: (int) $cfg['timeout_seconds'],
            retryAttempts: (int) $cfg['retry_attempts'],
            retryDelayMs: (int) $cfg['retry_delay_ms'],
        );
    }

    private function makeGemini(): LlmClient
    {
        $cfg = config('ocr.gemini');
        if (empty($cfg['api_key'])) {
            throw new RuntimeException('GEMINI_API_KEY not set');
        }
        return new GeminiLlmClient(
            apiKey: $cfg['api_key'],
            modelMap: [
                'classifier' => $cfg['model_classifier'],
                'extractor' => $cfg['model_extractor'],
                'judge' => $cfg['model_judge'],
            ],
            timeoutSeconds: (int) $cfg['timeout_seconds'],
            retryAttempts: (int) $cfg['retry_attempts'],
            retryDelayMs: (int) $cfg['retry_delay_ms'],
        );
    }
}
