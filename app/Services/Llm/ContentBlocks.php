<?php

namespace App\Services\Llm;

use RuntimeException;

/**
 * Provider-agnostic content block helpers. Each adapter converts these into
 * the vendor-specific JSON shape.
 */
class ContentBlocks
{
    public static function text(string $text): array
    {
        return ['type' => 'text', 'text' => $text];
    }

    public static function imageFromPath(string $absolutePath): array
    {
        $mime = mime_content_type($absolutePath) ?: 'image/jpeg';
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            throw new RuntimeException("Unsupported image MIME: {$mime}");
        }
        return [
            'type' => 'image',
            'mime' => $mime,
            'data_base64' => base64_encode((string) file_get_contents($absolutePath)),
        ];
    }

    public static function pdfFromPath(string $absolutePath): array
    {
        return [
            'type' => 'pdf',
            'mime' => 'application/pdf',
            'data_base64' => base64_encode((string) file_get_contents($absolutePath)),
        ];
    }

    /**
     * Build a single image-or-pdf block from a file path + mime.
     */
    public static function fileBlock(string $absolutePath, string $mime): array
    {
        if ($mime === 'application/pdf') {
            return self::pdfFromPath($absolutePath);
        }
        return self::imageFromPath($absolutePath);
    }

    /**
     * Decode JSON from a model's text response, tolerating ```json fences.
     * Includes a snippet of the offending response in the error message for debugging.
     */
    public static function extractJson(string $raw): array
    {
        $clean = trim($raw);
        if (str_starts_with($clean, '```')) {
            $clean = preg_replace('/^```(?:json)?\s*/i', '', $clean);
            $clean = preg_replace('/```\s*$/', '', $clean);
            $clean = trim((string) $clean);
        }
        $first = strpos($clean, '{');
        $last = strrpos($clean, '}');
        if ($first === false || $last === false || $last < $first) {
            throw new RuntimeException(
                'No JSON object found in LLM response. Raw (first 300 chars): '
                    . substr($raw, 0, 300)
            );
        }
        $jsonStr = substr($clean, $first, $last - $first + 1);
        $decoded = json_decode($jsonStr, true);
        if (! is_array($decoded)) {
            throw new RuntimeException(
                'Invalid JSON in LLM response: ' . json_last_error_msg()
                    . ' | Snippet: ' . substr($jsonStr, 0, 300)
            );
        }
        return $decoded;
    }
}
