<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-gray-900">Upload tài liệu</h1>
        <p class="mt-1 text-sm text-gray-600">
            Chấp nhận ảnh hoặc PDF, tối đa 25MB. Hỗ trợ tiếng Việt, Anh, Trung.
            Tài liệu Word/Excel xin lưu sang PDF trước khi tải lên.
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
                    wire:loading.attr="disabled"
                    wire:target="submit"
                    @disabled(! $file)
                    class="inline-flex items-center gap-2 rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800 disabled:bg-gray-300 disabled:cursor-not-allowed transition">
                <svg wire:loading wire:target="submit" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor"
                          d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4zm2 5.3A7.96 7.96 0 014 12H0c0 3 1.1 5.8 3 7.9l3-2.6z"></path>
                </svg>
                <span wire:loading.remove wire:target="submit">Bắt đầu xử lý</span>
                <span wire:loading wire:target="submit">Đang xử lý…</span>
            </button>

            <p class="text-xs text-gray-500" wire:loading wire:target="submit">
                Quá trình mất khoảng 30-45 giây. Vui lòng đừng đóng tab.
            </p>
        </div>
    </form>
</div>
