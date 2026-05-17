<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\OcrDocument
 */
class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $extraction = $this->whenLoaded('extraction');
        $view = $request->query('view') === 'masked' ? 'masked' : 'raw';

        return [
            'document_id' => $this->id,
            'status' => $this->status,
            'original_name' => $this->original_name,
            'mime' => $this->mime,
            'size_bytes' => $this->size_bytes,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'extraction' => $extraction
                ? (new ExtractionResource($extraction))->withView($view)
                : null,
        ];
    }
}
