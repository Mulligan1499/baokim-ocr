<?php

namespace App\Services\Storage;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class DocumentStorageService
{
    public function hashFile(UploadedFile $file): string
    {
        return hash_file('sha256', $file->getRealPath());
    }

    public function store(UploadedFile $file, string $hash): string
    {
        $disk = config('ocr.storage_disk', 'local');
        $prefix = config('ocr.storage_path_prefix', 'ocr');
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());

        $relativePath = sprintf(
            '%s/%s/%s/%s.%s',
            $prefix,
            now()->format('Y'),
            now()->format('m'),
            $hash,
            $ext,
        );

        if (! Storage::disk($disk)->exists($relativePath)) {
            Storage::disk($disk)->putFileAs(
                dirname($relativePath),
                $file,
                basename($relativePath),
            );
        }

        return $relativePath;
    }

    public function exists(string $relativePath): bool
    {
        return Storage::disk(config('ocr.storage_disk', 'local'))->exists($relativePath);
    }

    public function readContent(string $relativePath): string
    {
        return Storage::disk(config('ocr.storage_disk', 'local'))->get($relativePath);
    }

    public function absolutePath(string $relativePath): string
    {
        return Storage::disk(config('ocr.storage_disk', 'local'))->path($relativePath);
    }
}
