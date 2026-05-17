<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-gray-900">Upload tài liệu OCR</h1>
        <p class="mt-1 text-sm text-gray-600">
            Nhận ảnh (JPEG/PNG/WEBP) hoặc PDF tới 25MB. VI / EN / ZH đều OK.
            Word/Excel chưa hỗ trợ — Save As → PDF trước.
        </p>
    </div>

    <form wire:submit="submit" class="space-y-4">
        <label for="file"
               class="flex flex-col items-center justify-center w-full h-48 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer bg-white hover:bg-gray-50 transition">
            <div class="flex flex-col items-center justify-center pt-5 pb-6">
                @if ($file)
                    <p class="mb-2 text-sm text-gray-900 font-medium">{{ $file->getClientOriginalName() }}</p>
                    <p class="text-xs text-gray-500">{{ number_format($file->getSize() / 1024, 1) }} KB</p>
                @else
                    <svg class="w-8 h-8 mb-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    <p class="mb-1 text-sm text-gray-600"><span class="font-semibold">Click để chọn</span> hoặc kéo thả file</p>
                    <p class="text-xs text-gray-500">JPEG · PNG · WEBP · PDF · max 25MB</p>
                @endif
            </div>
            <input id="file" type="file" wire:model="file" class="hidden"
                   accept="image/jpeg,image/png,image/webp,application/pdf">
        </label>

        @error('file')
            <p class="text-sm text-red-600">{{ $message }}</p>
        @enderror

        @if ($errorMessage)
            <div class="rounded-md bg-red-50 border border-red-200 p-3">
                <p class="text-sm text-red-700">{{ $errorMessage }}</p>
            </div>
        @endif

        <div class="flex items-center gap-3">
            <button type="submit"
                    @disabled(! $file || $busy)
                    class="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800 disabled:bg-gray-300 disabled:cursor-not-allowed transition">
                @if ($busy)
                    Đang xử lý 7 stages…
                @else
                    Upload + OCR
                @endif
            </button>

            @if ($busy)
                <p class="text-xs text-gray-500">3 LLM calls ~30-45s. Đừng đóng tab.</p>
            @endif
        </div>
    </form>

    <div class="text-xs text-gray-500 border-t pt-4">
        Pipeline: Stage 0 validate → Stage 1 classify ({{ config('ocr.gemini.model_classifier') }})
        → Stage 2 extract → Stage 3 validate (rule + judge) → Stage 4 PII mask
        → Stage 5 aggregate → Stage 6 persist
    </div>
</div>
