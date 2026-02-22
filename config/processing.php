<?php

$retryBackoff = array_values(array_filter(
    array_map(
        static fn (string $value): int => (int) trim($value),
        explode(',', (string) env('PROCESSING_RETRY_BACKOFF', '5,15,45')),
    ),
    static fn (int $seconds): bool => $seconds > 0,
));

if ($retryBackoff === []) {
    $retryBackoff = [5, 15, 45];
}

return [
    'queue_connection' => env('PROCESSING_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'sync')),
    'ocr_provider' => env('PROCESSING_OCR_PROVIDER', 'simulated'),
    'classification_provider' => env('PROCESSING_CLASSIFICATION_PROVIDER', 'simulated'),
    'retry_attempts' => (int) env('PROCESSING_RETRY_ATTEMPTS', 3),
    'retry_backoff' => $retryBackoff,
    'scan_wait_delay_seconds' => (int) env('PROCESSING_SCAN_WAIT_DELAY_SECONDS', 5),
    'classification_queues' => [
        'contract' => 'queue.classify.contract',
        'tax' => 'queue.classify.tax',
        'invoice' => 'queue.classify.invoice',
        'general' => 'queue.classify.general',
    ],
];
