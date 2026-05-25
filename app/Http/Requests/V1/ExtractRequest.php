<?php

namespace App\Http\Requests\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

/**
 * AC-E01, AC-E02, AC-E03 — input validation cho POST /api/v1/ocr/extract.
 *
 * Error schema chuẩn R8: {error_code, message_vi, message_en, request_id}.
 */
class ExtractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxKb = (int) config('ocr.max_file_size_mb', 10) * 1024;
        $allowed = implode(',', config('ocr.allowed_mimes'));

        return [
            'file' => [
                'bail',
                'required',
                'file',
                'max:' . $maxKb,
                'mimetypes:' . $allowed,
                function ($attribute, $value, $fail) {
                    // AC-E01: file 0 byte → EMPTY_FILE. is_object guard tránh crash khi $value array.
                    if (is_object($value) && method_exists($value, 'getSize') && $value->getSize() === 0) {
                        $fail('The file is empty.');
                    }
                },
            ],
        ];
    }

    /**
     * prepareForValidation chạy TRƯỚC rules, throw early cho case multi-file.
     * Reject:
     *   (a) Array notation `file[]=A` (bất kể count) — không cho dùng array notation
     *   (b) 2 field khác nhau (`file` + `file2`) — `allFiles()` > 1 key
     */
    protected function prepareForValidation(): void
    {
        $allFiles = $this->allFiles();

        // Case (a): bất kỳ entry nào là array → array notation
        foreach ($allFiles as $entry) {
            if (is_array($entry)) {
                $this->rejectMultipleFiles();
            }
        }

        // Case (b): > 1 key trong allFiles
        if (count($allFiles) > 1) {
            $this->rejectMultipleFiles();
        }
    }

    private function rejectMultipleFiles(): void
    {
        throw new HttpResponseException(
            response()->json([
                'error_code' => 'MULTIPLE_FILES_NOT_ALLOWED',
                'message_vi' => 'Chỉ chấp nhận 1 file mỗi lần upload. Vui lòng tải lại với 1 file duy nhất.',
                'message_en' => 'Only one file per upload allowed.',
                'request_id' => (string) Str::uuid(),
            ], 400)
        );
    }

    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors();
        $maxMb = (int) config('ocr.max_file_size_mb', 10);

        // Map Laravel validation key → AC error code + HTTP status.
        [$code, $messageVi, $messageEn, $status] = $this->translateFailure($errors, $maxMb);

        throw new HttpResponseException(
            response()->json([
                'error_code' => $code,
                'message_vi' => $messageVi,
                'message_en' => $messageEn,
                'request_id' => (string) Str::uuid(),
            ], $status)
        );
    }

    private function translateFailure($errors, int $maxMb): array
    {
        if ($errors->has('file')) {
            $msg = $errors->first('file');

            if (str_contains($msg, 'required')) {
                return [
                    'MISSING_FILE',
                    'Vui lòng đính kèm file (multipart field "file").',
                    'File field is required.',
                    422,
                ];
            }
            if (str_contains($msg, 'empty')) {
                return [
                    'EMPTY_FILE',
                    'File rỗng hoặc không có nội dung.',
                    'File is empty or has no content.',
                    400,
                ];
            }
            if (str_contains($msg, 'must not be greater')
                || str_contains($msg, 'may not be greater')
            ) {
                return [
                    'FILE_TOO_LARGE',
                    "File vượt quá {$maxMb}MB.",
                    "File exceeds {$maxMb}MB limit.",
                    413,
                ];
            }
            if (str_contains($msg, 'mimetypes') || str_contains($msg, 'type')) {
                return [
                    'INVALID_FILE_FORMAT',
                    'Định dạng không hợp lệ. Chỉ nhận JPEG, PNG, WEBP, PDF.',
                    'Invalid file format. Only JPEG, PNG, WEBP, PDF are accepted.',
                    400,
                ];
            }
        }

        return [
            'VALIDATION_FAILED',
            'Dữ liệu không hợp lệ.',
            'Validation failed.',
            422,
        ];
    }
}
