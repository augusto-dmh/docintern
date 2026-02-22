<?php

namespace App\Listeners;

use App\Events\DocumentProcessingEvent;
use App\Jobs\Processing\AuditLogConsumerJob;
use App\Jobs\Processing\OcrExtractionConsumerJob;
use App\Jobs\Processing\VirusScanConsumerJob;

class DispatchDocumentUploadFanout
{
    public function handle(DocumentProcessingEvent $event): void
    {
        if ($event->event !== 'document.uploaded') {
            return;
        }

        $payload = $event->toPayload();

        VirusScanConsumerJob::dispatch($payload)
            ->onConnection('rabbitmq')
            ->onQueue('queue.virus-scan');

        AuditLogConsumerJob::dispatch($payload)
            ->onConnection('rabbitmq')
            ->onQueue('queue.audit-log');

        OcrExtractionConsumerJob::dispatch($payload)
            ->onConnection('rabbitmq')
            ->onQueue('queue.ocr-extraction');
    }
}
