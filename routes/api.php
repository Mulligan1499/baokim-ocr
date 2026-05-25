<?php

use App\Http\Controllers\Api\V1\OcrV1Controller;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.apikey')->prefix('v1')->group(function () {
    Route::post('/ocr/extract', [OcrV1Controller::class, 'extract'])
        ->name('v1.ocr.extract');

    Route::get('/ocr/history', [OcrV1Controller::class, 'history'])
        ->name('v1.ocr.history.list');

    // Accept cả UUID (AC-05 standard) lẫn numeric id (alias debug-friendly).
    Route::get('/ocr/history/{key}', [OcrV1Controller::class, 'showByKey'])
        ->where('key', '[0-9a-fA-F\-]{36}|[0-9]+')
        ->name('v1.ocr.history.show');
});
