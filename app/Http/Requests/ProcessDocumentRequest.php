<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ProcessDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxKb = config('ocr.max_file_size_mb', 10) * 1024;
        $allowed = implode(',', config('ocr.allowed_mimes'));

        return [
            'file' => [
                'required',
                'file',
                'max:' . $maxKb,
                'mimetypes:' . $allowed,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Vui lòng upload file (form field name: file).',
            'file.max' => 'File quá lớn. Tối đa ' . config('ocr.max_file_size_mb') . 'MB.',
            'file.mimetypes' => 'Định dạng file không hỗ trợ. Chỉ nhận: JPEG, PNG, WEBP, PDF. '
                . 'Word/Excel chưa hỗ trợ — vui lòng Save As → PDF trước khi upload.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422)
        );
    }
}
