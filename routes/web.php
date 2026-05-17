<?php

use App\Livewire\DocumentHistory;
use App\Livewire\ExtractionResult;
use App\Livewire\UploadDocument;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/ocr');

Route::get('/ocr', UploadDocument::class)->name('ocr.upload');

Route::get('/ocr/history', DocumentHistory::class)->name('ocr.history');

Route::get('/ocr/{id}', ExtractionResult::class)
    ->where('id', '[0-9]+')
    ->name('ocr.result');
