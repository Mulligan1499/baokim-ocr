<?php

namespace App\Livewire;

use App\Services\Ocr\DocumentUploadService;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Upload form cho KSNB. Reuse DocumentUploadService (BKM03 — không
 * duplicate logic giữa API và UI). Sync mode QUEUE_CONNECTION=sync nên
 * pipeline chạy ngay trong request, redirect đến result page khi xong.
 */
class UploadDocument extends Component
{
    use WithFileUploads;

    #[Validate('required|file|max:25600|mimes:jpeg,jpg,png,webp,pdf')]
    public $file;

    public bool $busy = false;
    public ?string $errorMessage = null;

    /**
     * Livewire hook — chạy ngay khi user chọn file mới qua input.
     * Clear message lỗi của lần upload trước, tránh KSNB nhầm.
     */
    public function updatedFile(): void
    {
        $this->errorMessage = null;
    }

    public function submit(DocumentUploadService $uploadService): void
    {
        // Clear TRƯỚC validate — phòng case validate fail vẫn còn message cũ
        $this->errorMessage = null;
        $this->validate();

        $this->busy = true;

        try {
            @set_time_limit(0);
            [$doc] = $uploadService->handle($this->file, apiKeyLabel: 'web-ui');
            $this->redirect(route('ocr.result', ['id' => $doc->id]), navigate: true);
        } catch (\Throwable $e) {
            $this->busy = false;
            // Log full exception cho dev/ops debug, KSNB chỉ thấy message thân thiện.
            Log::error('upload.failed', [
                'exception' => class_basename($e),
                'message' => $e->getMessage(),
                'file_name' => $this->file?->getClientOriginalName(),
            ]);
            $this->errorMessage = 'Không xử lý được tài liệu này. Vui lòng thử lại hoặc đổi file khác. '
                . 'Nếu vẫn lỗi, liên hệ kỹ thuật.';
        }
    }

    public function render()
    {
        return view('livewire.upload-document');
    }
}
