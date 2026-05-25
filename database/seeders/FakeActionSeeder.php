<?php

namespace Database\Seeders;

use App\Models\OcrDocument;
use App\Models\OcrExtraction;
use App\Models\OcrUserAction;
use App\Models\OcrUserActionText;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Tạo data giả cho demo `ocr:analyze-actions`:
 * - 1 doc CCCD với 12 actions (10 edit_then_copy → trigger Pattern A)
 * - 1 doc contract_zh với 8 skip actions (→ trigger Pattern E)
 * - 1 doc invoice với 5 mark_wrong actions (→ trigger Pattern C — hard signal)
 *
 * Chạy: php artisan db:seed --class=FakeActionSeeder
 */
class FakeActionSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCccdEditPattern();
        $this->seedContractZhSkipPattern();
        $this->seedInvoiceMarkWrongPattern();

        $this->command->info('Seeded fake actions:');
        $this->command->table(['Pattern', 'Count'], [
            ['A — CCCD so_cccd edit_then_copy', '10'],
            ['E — contract_zh ethnicity skip', '20'],
            ['C — invoice total_amount mark_wrong', '5'],
        ]);
    }

    private function seedCccdEditPattern(): void
    {
        $doc = OcrDocument::create([
            'hash' => str_repeat('a', 64),
            'original_name' => 'fake-cccd-pattern-A.jpg',
            'mime' => 'image/jpeg',
            'size_bytes' => 50000,
            'storage_path' => 'fake/pattern-A.jpg',
            'status' => 'done',
            'uploaded_via_api_key_label' => 'seeder',
        ]);

        OcrExtraction::create([
            'document_id' => $doc->id,
            'doc_type' => 'cccd',
            'language_detected' => 'vi',
            'page_count' => 1,
            'key_values' => ['so_cccd' => '00123456789O'],
            'key_values_masked' => ['so_cccd' => '00123****89O'],
            'confidence_overall' => 0.85,
            'confidence_per_field' => ['so_cccd' => 0.6],
            'quality' => 'medium',
            'requires_review' => true,
        ]);

        $sessionId = (string) Str::uuid();
        for ($i = 0; $i < 10; $i++) {
            $action = OcrUserAction::create([
                'document_id' => $doc->id,
                'field_key' => 'so_cccd',
                'action_type' => OcrUserAction::ACTION_EDIT_THEN_COPY,
                'session_id' => $sessionId,
                'duration_ms' => 15000 + $i * 1000,
                'ksnb_user_label' => 'seeder',
                'created_at' => now()->subDays(2)->addMinutes($i * 5),
            ]);
            OcrUserActionText::create([
                'action_id' => $action->id,
                'original_value' => '00123456789O',
                'final_value' => '001234567890',
            ]);
        }
        for ($i = 0; $i < 2; $i++) {
            $action = OcrUserAction::create([
                'document_id' => $doc->id,
                'field_key' => 'so_cccd',
                'action_type' => OcrUserAction::ACTION_COPY_RAW,
                'session_id' => $sessionId,
                'duration_ms' => 3000,
                'ksnb_user_label' => 'seeder',
                'created_at' => now()->subDays(2)->addMinutes($i * 5),
            ]);
            OcrUserActionText::create([
                'action_id' => $action->id,
                'original_value' => '001234567890',
                'final_value' => '001234567890',
            ]);
        }
    }

    private function seedContractZhSkipPattern(): void
    {
        $doc = OcrDocument::create([
            'hash' => str_repeat('b', 64),
            'original_name' => 'fake-contract-zh-pattern-E.pdf',
            'mime' => 'application/pdf',
            'size_bytes' => 800000,
            'storage_path' => 'fake/pattern-E.pdf',
            'status' => 'done',
            'uploaded_via_api_key_label' => 'seeder',
        ]);

        OcrExtraction::create([
            'document_id' => $doc->id,
            'doc_type' => 'contract_zh',
            'language_detected' => 'zh',
            'page_count' => 3,
            'key_values' => ['party_a' => 'XYZ Ltd', 'ethnicity' => 'Han'],
            'key_values_masked' => ['party_a' => 'XYZ Ltd', 'ethnicity' => 'Han'],
            'confidence_overall' => 0.9,
            'confidence_per_field' => ['party_a' => 0.95, 'ethnicity' => 0.7],
            'quality' => 'high',
            'requires_review' => false,
        ]);

        $sessionId = (string) Str::uuid();
        for ($i = 0; $i < 20; $i++) {
            $action = OcrUserAction::create([
                'document_id' => $doc->id,
                'field_key' => 'ethnicity',
                'action_type' => OcrUserAction::ACTION_SKIP,
                'session_id' => $sessionId,
                'duration_ms' => 1000,
                'ksnb_user_label' => 'seeder',
                'created_at' => now()->subDays(3)->addHours($i),
            ]);
            OcrUserActionText::create([
                'action_id' => $action->id,
                'original_value' => 'Han',
                'final_value' => null,
            ]);
        }
    }

    private function seedInvoiceMarkWrongPattern(): void
    {
        $doc = OcrDocument::create([
            'hash' => str_repeat('c', 64),
            'original_name' => 'fake-invoice-pattern-C.png',
            'mime' => 'image/png',
            'size_bytes' => 200000,
            'storage_path' => 'fake/pattern-C.png',
            'status' => 'done',
            'uploaded_via_api_key_label' => 'seeder',
        ]);

        OcrExtraction::create([
            'document_id' => $doc->id,
            'doc_type' => 'invoice',
            'language_detected' => 'vi',
            'page_count' => 1,
            'key_values' => ['total_amount' => '15000000', 'mst' => '0123456789'],
            'key_values_masked' => ['total_amount' => '15000000', 'mst' => '01234****89'],
            'confidence_overall' => 0.92,
            'confidence_per_field' => ['total_amount' => 0.95, 'mst' => 0.9],
            'quality' => 'high',
            'requires_review' => false,
        ]);

        $sessionId = (string) Str::uuid();
        for ($i = 0; $i < 5; $i++) {
            $action = OcrUserAction::create([
                'document_id' => $doc->id,
                'field_key' => 'total_amount',
                'action_type' => OcrUserAction::ACTION_MARK_WRONG,
                'session_id' => $sessionId,
                'duration_ms' => 35000,
                'ksnb_user_label' => 'seeder',
                'created_at' => now()->subDays(1)->addHours($i * 2),
            ]);
            OcrUserActionText::create([
                'action_id' => $action->id,
                'original_value' => '15000000',
                'final_value' => null,
            ]);
        }
    }
}
