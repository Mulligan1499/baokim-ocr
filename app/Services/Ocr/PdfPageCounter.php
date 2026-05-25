<?php

namespace App\Services\Ocr;

use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Đếm số trang PDF — dùng cho:
 *   - Stage 0: enforce OCR_MAX_PAGES limit trước khi gọi LLM
 *   - Stage 6: lưu page_count chính xác vào ocr_extractions (AC-02)
 *
 * Image files (jpg/png/webp) luôn = 1 trang.
 */
class PdfPageCounter
{
    public function count(string $absoluteFilePath, string $mime): int
    {
        if ($mime !== 'application/pdf') {
            return 1;
        }

        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($absoluteFilePath);
            return count($pdf->getPages());
        } catch (Throwable $e) {
            // PDF hỏng / encrypted — return 1 để pipeline tiếp tục, Stage 2 sẽ phát hiện.
            return 1;
        }
    }
}
