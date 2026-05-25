<?php

namespace App\Support;

/**
 * Parse text chứa marker `=== Trang N ===` (Stage 2 prompt yêu cầu Gemini insert
 * khi PDF multi-page) → array of pages cho UI render tách block.
 *
 * Fallback: nếu text không có marker (single page hoặc Gemini không tuân thủ) →
 * return 1 page duy nhất chứa toàn bộ text.
 */
class PageMarkerParser
{
    private const PATTERN = '/^===\s*Trang\s+(\d+)\s*===\s*$/mu';

    /**
     * @return array<int, array{page_no:int|null, content:string}>
     */
    public static function parse(?string $text): array
    {
        $text = (string) $text;
        if (trim($text) === '') {
            return [];
        }

        if (! preg_match(self::PATTERN, $text)) {
            // No markers → single page, return content as-is (no heading)
            return [['page_no' => null, 'content' => $text]];
        }

        // Split keeping markers as delimiters
        $parts = preg_split(self::PATTERN, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        $pages = [];
        $currentPageNo = null;
        $currentContent = '';

        // Nội dung trước marker đầu tiên (preamble) — thường rỗng nhưng phòng case
        $first = trim($parts[0] ?? '');
        if ($first !== '' && ! ctype_digit($first)) {
            $pages[] = ['page_no' => null, 'content' => $first];
            array_shift($parts);
        }

        // Alternate: [pageNo, content, pageNo, content, ...]
        for ($i = 0; $i < count($parts); $i += 2) {
            $pageNo = (int) ($parts[$i] ?? 0);
            $content = trim($parts[$i + 1] ?? '');
            if ($content !== '') {
                $pages[] = ['page_no' => $pageNo, 'content' => $content];
            }
        }

        return $pages ?: [['page_no' => null, 'content' => $text]];
    }
}
