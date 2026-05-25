<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full result envelope cho AC-01..06.
 *
 * @mixin \App\Models\OcrDocument
 */
class DocumentV1Resource extends JsonResource
{
    private bool $cachedHit = false;

    public function withCachedFlag(bool $cached): self
    {
        $this->cachedHit = $cached;
        return $this;
    }

    public function toArray(Request $request): array
    {
        $extraction = $this->whenLoaded('extraction');

        $payload = [
            'request_id' => $this->request_id,
            'status' => $this->status,
            'document_id' => $this->id,
            'file_name' => $this->original_name,
            'file_hash' => $this->hash,
            'file_size_bytes' => $this->size_bytes,
            'mime' => $this->mime,
            'uploaded_at' => $this->created_at?->toIso8601String(),
            'uploaded_by' => $this->uploaded_via_api_key_label,
            'cached' => $this->cachedHit,
        ];

        if ($extraction && $this->resource->extraction) {
            $extraction->setRelation('document', $this->resource);
            $payload['result'] = (new ExtractionV1Resource($extraction))->toArray($request);
        } else {
            $payload['result'] = null;
        }

        if ($this->status === 'failed') {
            $payload['error_message_vi'] = 'Không thể xử lý tài liệu này. Vui lòng thử lại hoặc upload file khác.';
        }

        return $payload;
    }
}
