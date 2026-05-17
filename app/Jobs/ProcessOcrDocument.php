<?php

namespace App\Jobs;

use App\Models\OcrDocument;
use App\Repositories\OcrAuditLogRepository;
use App\Repositories\OcrDocumentRepository;
use App\Services\Ocr\OcrPipelineOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessOcrDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 2;

    public function __construct(public int $documentId) {}

    public function handle(OcrPipelineOrchestrator $orchestrator): void
    {
        $orchestrator->run($this->documentId);
    }

    public function failed(Throwable $e): void
    {
        $documents = app(OcrDocumentRepository::class);
        $audit = app(OcrAuditLogRepository::class);

        $documents->updateStatus(
            $this->documentId,
            OcrDocument::STATUS_FAILED,
            substr($e->getMessage(), 0, 1000),
        );

        $audit->log([
            'document_id' => $this->documentId,
            'stage' => 1,
            'event' => 'job_failed_final',
            'payload' => [
                'exception' => class_basename($e),
                'message' => substr($e->getMessage(), 0, 500),
            ],
        ]);
    }
}
