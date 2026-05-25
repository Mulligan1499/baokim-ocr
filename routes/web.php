<?php

use App\Livewire\DocumentHistory;
use App\Livewire\ExtractionResult;
use App\Livewire\UploadDocument;
use App\Models\OcrDocument;
use App\Services\Storage\DocumentStorageService;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/ocr');

Route::get('/ocr', UploadDocument::class)->name('ocr.upload');

Route::get('/ocr/history', DocumentHistory::class)->name('ocr.history');

Route::get('/ocr/{id}', ExtractionResult::class)
    ->where('id', '[0-9]+')
    ->name('ocr.result');

Route::get('/ocr/{id}/file', function (int $id, DocumentStorageService $storage) {
    $doc = OcrDocument::findOrFail($id);
    abort_unless($storage->exists($doc->storage_path), 404);
    return response()->file($storage->absolutePath($doc->storage_path), [
        'Content-Type' => $doc->mime,
        'Content-Disposition' => 'inline; filename="' . addslashes($doc->original_name) . '"',
    ]);
})->where('id', '[0-9]+')->name('ocr.file');
