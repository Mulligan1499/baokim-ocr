<?php

namespace App\Livewire;

use App\Models\OcrDocument;
use App\Repositories\OcrUserActionRepository;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Hiển thị kết quả OCR + capture implicit action của KSNB (copy raw,
 * edit then copy, skip, mark wrong) làm input cho feedback loop.
 *
 * Mỗi action gửi qua wire method `recordAction` → ghi vào ocr_user_actions.
 * Agent `ocr:analyze-actions` đọc table này để propose skill update.
 */
class ExtractionResult extends Component
{
    public OcrDocument $doc;
    public string $sessionId;

    /** @var array<string, array{value:string, edited_value:string, action:?string}> */
    public array $fieldState = [];

    public function mount(int $id): void
    {
        $this->doc = OcrDocument::with('extraction')->findOrFail($id);
        $this->sessionId = (string) Str::uuid();

        $keyValues = $this->doc->extraction?->key_values ?? [];
        foreach ($keyValues as $key => $value) {
            $this->fieldState[$key] = [
                'value' => (string) $value,
                'edited_value' => (string) $value,
                'action' => null,
            ];
        }
    }

    public function recordAction(
        string $fieldKey,
        string $actionType,
        ?string $finalValue,
        ?int $durationMs,
        OcrUserActionRepository $actions,
    ): void {
        $original = $this->fieldState[$fieldKey]['value'] ?? null;

        $actions->create([
            'document_id' => $this->doc->id,
            'field_key' => $fieldKey,
            'action_type' => $actionType,
            'original_value' => $original,
            'final_value' => $finalValue,
            'session_id' => $this->sessionId,
            'duration_ms' => $durationMs,
            'ksnb_user_label' => 'web-ui',
        ]);

        $this->fieldState[$fieldKey]['action'] = $actionType;
        if ($finalValue !== null) {
            $this->fieldState[$fieldKey]['edited_value'] = $finalValue;
        }
    }

    public function render()
    {
        return view('livewire.extraction-result');
    }
}
