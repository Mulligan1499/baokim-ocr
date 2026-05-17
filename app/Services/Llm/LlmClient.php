<?php

namespace App\Services\Llm;

/**
 * Provider-agnostic LLM vision client. Both Claude (Anthropic) and Gemini (Google)
 * adapters implement this so Stage 1/2/3 services don't depend on a vendor.
 *
 * Switch via env OCR_LLM_PROVIDER=anthropic|gemini.
 */
interface LlmClient
{
    /**
     * Send a multimodal message.
     *
     * @param  string  $modelLogicalName  'classifier' or 'extractor' or 'judge' — provider translates to concrete model id
     * @param  string  $systemPrompt
     * @param  array   $userContent       array of provider-agnostic blocks (see static helpers below)
     * @param  int     $maxTokens
     * @return array {
     *   content: string,       // raw model text response
     *   latency_ms: int,
     *   input_tokens: int,
     *   output_tokens: int,
     *   model: string          // concrete model id
     * }
     */
    public function messages(
        string $modelLogicalName,
        string $systemPrompt,
        array $userContent,
        int $maxTokens = 4096,
    ): array;

    /**
     * Provider name (for audit log).
     */
    public function providerName(): string;
}
