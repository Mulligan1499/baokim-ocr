<?php

namespace App\Http\Middleware;

use App\Support\ApiErrorResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('ocr.api_key');
        $header = config('ocr.api_key_header', 'X-API-Key');
        $provided = $request->header($header);

        // V1 routes dùng error schema chuẩn AC R8. V0 (legacy) giữ shape cũ cho UI Livewire.
        $isV1 = $request->is('api/v1/*');

        if (! $expected) {
            return $isV1
                ? ApiErrorResponse::make(
                    'SERVER_MISCONFIGURED',
                    'Hệ thống chưa cấu hình khóa API.',
                    'API key not configured on server.',
                    500,
                )
                : response()->json(['error' => 'API key not configured on server'], 500);
        }

        if (! $provided || ! hash_equals($expected, $provided)) {
            $resp = $isV1
                ? ApiErrorResponse::make(
                    'UNAUTHORIZED',
                    'Khóa API không hợp lệ hoặc thiếu header ' . $header . '.',
                    'Invalid or missing API key. Provide header ' . $header . '.',
                    401,
                )
                : response()->json([
                    'error' => 'Invalid or missing API key',
                    'hint' => 'Provide header ' . $header,
                ], 401);

            $resp->header('WWW-Authenticate', 'ApiKey realm="ocr-api"');
            return $resp;
        }

        $request->attributes->set('api_key_label', 'default');

        return $next($request);
    }
}
