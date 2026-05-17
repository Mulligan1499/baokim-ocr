<?php

namespace App\Livewire;

use App\Models\OcrDocument;
use App\Models\OcrUserAction;
use App\Repositories\OcrUserActionRepository;
use Illuminate\Support\Str;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Hiển thị kết quả OCR + capture implicit action của KSNB (copy raw,
 * edit then copy, skip, mark wrong) + overall comment làm input cho
 * feedback loop agent.
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

    #[Validate('nullable|string|max:2000')]
    public string $overallNote = '';
    public bool $overallNoteSubmitted = false;

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

    /**
     * Wire from Livewire frontend khi KSNB click Copy/Skip/Wrong.
     * Note edited_value đến từ frontend (Livewire wire:model sync).
     */
    public function recordAction(
        string $fieldKey,
        string $actionType,
        ?int $durationMs,
        OcrUserActionRepository $actions,
    ): void {
        $original = $this->fieldState[$fieldKey]['value'] ?? null;
        $final = $this->fieldState[$fieldKey]['edited_value'] ?? null;

        $actions->create([
            'document_id' => $this->doc->id,
            'field_key' => $fieldKey,
            'action_type' => $actionType,
            'original_value' => $original,
            'final_value' => $actionType === OcrUserAction::ACTION_COPY_RAW
                || $actionType === OcrUserAction::ACTION_EDIT_THEN_COPY
                ? $final : null,
            'session_id' => $this->sessionId,
            'duration_ms' => $durationMs,
            'ksnb_user_label' => 'web-ui',
        ]);

        $this->fieldState[$fieldKey]['action'] = $actionType;
    }

    /**
     * Hoàn tác action vừa thực hiện cho 1 field — KSNB click nhầm có thể undo
     * và thao tác lại. Chỉ rollback được action trong session hiện tại + chưa
     * được agent analyzed.
     */
    public function rollbackAction(string $fieldKey, OcrUserActionRepository $actions): void
    {
        $actions->deleteLatestForField($this->doc->id, $fieldKey, $this->sessionId);
        $this->fieldState[$fieldKey]['action'] = null;
    }

    public function submitOverallComment(OcrUserActionRepository $actions): void
    {
        $this->validate();

        if (trim($this->overallNote) === '') {
            return;
        }

        $actions->create([
            'document_id' => $this->doc->id,
            'field_key' => '_global',
            'action_type' => OcrUserAction::ACTION_OVERALL_COMMENT,
            'original_value' => null,
            'final_value' => null,
            'note' => $this->overallNote,
            'session_id' => $this->sessionId,
            'duration_ms' => null,
            'ksnb_user_label' => 'web-ui',
        ]);

        $this->overallNoteSubmitted = true;
    }

    public function render()
    {
        return view('livewire.extraction-result');
    }
}
