<?php

namespace App\Http\Middleware;

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

        if (! $expected) {
            return response()->json([
                'error' => 'API key not configured on server',
            ], 500);
        }

        if (! $provided || ! hash_equals($expected, $provided)) {
            return response()->json([
                'error' => 'Invalid or missing API key',
                'hint' => 'Provide header ' . $header,
            ], 401);
        }

        $request->attributes->set('api_key_label', 'default');

        return $next($request);
    }
}
