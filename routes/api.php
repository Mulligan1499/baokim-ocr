<?php

use App\Http\Controllers\Api\OcrController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.apikey')->group(function () {
    Route::post('/ocr/process', [OcrController::class, 'process'])->name('ocr.process');
    Route::get('/ocr', [OcrController::class, 'index'])->name('ocr.index');
    Route::get('/ocr/{id}', [OcrController::class, 'show'])
        ->where('id', '[0-9]+')
        ->name('ocr.show');
});
