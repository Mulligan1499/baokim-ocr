<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Helper trả error schema:
 *   { error_code, message_vi, message_en, request_id, retry_after? }
 */
class ApiErrorResponse
{
    public static function make(
        string $code,
        string $messageVi,
        string $messageEn,
        int $status = 400,
        ?int $retryAfter = null,
        ?string $requestId = null,
    ): JsonResponse {
        $body = [
            'error_code' => $code,
            'message_vi' => $messageVi,
            'message_en' => $messageEn,
            'request_id' => $requestId ?: (string) Str::uuid(),
        ];
        if ($retryAfter !== null) {
            $body['retry_after'] = $retryAfter;
        }

        $resp = response()->json($body, $status);
        if ($retryAfter !== null) {
            $resp->header('Retry-After', (string) $retryAfter);
        }
        return $resp;
    }
}
