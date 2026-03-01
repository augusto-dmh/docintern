<?php

$ocrProvider = (string) env('PROCESSING_OCR_PROVIDER', 'simulated');
$classificationProvider = (string) env('PROCESSING_CLASSIFICATION_PROVIDER', 'simulated');
$providerMode = env('DOCINTERN_PROVIDER_MODE', env('PROCESSING_PROVIDER_MODE'));

if (! is_string($providerMode) || trim($providerMode) === '') {
    $providerMode = $ocrProvider === 'live' && $classificationProvider === 'live'
        ? 'live'
        : 'simulated';
}

$providerMode = strtolower(trim((string) $providerMode));

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
    'provider_mode' => $providerMode,
    'supported_provider_modes' => ['simulated', 'live'],
    'queue_connection' => env('PROCESSING_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'sync')),
    'ocr_provider' => $ocrProvider,
    'classification_provider' => $classificationProvider,
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('PROCESSING_OPENAI_MODEL', 'gpt-4o-mini'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'timeout_seconds' => (int) env('PROCESSING_OPENAI_TIMEOUT', 15),
    ],
    'provider_circuit' => [
        'failure_threshold' => (int) env('PROCESSING_PROVIDER_CIRCUIT_FAILURE_THRESHOLD', 3),
        'cooldown_seconds' => (int) env('PROCESSING_PROVIDER_CIRCUIT_COOLDOWN_SECONDS', 60),
    ],
    'provider_degraded_requeue_delay_seconds' => (int) env('PROCESSING_PROVIDER_DEGRADED_REQUEUE_DELAY_SECONDS', 30),
    'retry_attempts' => (int) env('PROCESSING_RETRY_ATTEMPTS', 3),
    'retry_backoff' => $retryBackoff,
    'scan_wait_delay_seconds' => (int) env('PROCESSING_SCAN_WAIT_DELAY_SECONDS', 5),
    'live_required_contract' => [
        'exact' => [
            [
                'path' => 'processing.ocr_provider',
                'env' => 'PROCESSING_OCR_PROVIDER',
                'expected' => 'live',
            ],
            [
                'path' => 'processing.classification_provider',
                'env' => 'PROCESSING_CLASSIFICATION_PROVIDER',
                'expected' => 'live',
            ],
            [
                'path' => 'filesystems.default',
                'env' => 'FILESYSTEM_DISK',
                'expected' => 's3',
            ],
            [
                'path' => 'processing.queue_connection',
                'env' => 'PROCESSING_QUEUE_CONNECTION',
                'expected' => 'rabbitmq',
            ],
        ],
        'non_empty' => [
            [
                'path' => 'aws.region',
                'env' => 'AWS_DEFAULT_REGION',
            ],
            [
                'path' => 'filesystems.disks.s3.bucket',
                'env' => 'AWS_BUCKET',
            ],
            [
                'path' => 'queue.connections.rabbitmq.management.host',
                'env' => 'RABBITMQ_MANAGEMENT_HOST',
            ],
            [
                'path' => 'queue.connections.rabbitmq.management.username',
                'env' => 'RABBITMQ_MANAGEMENT_USER',
            ],
            [
                'path' => 'queue.connections.rabbitmq.management.password',
                'env' => 'RABBITMQ_MANAGEMENT_PASSWORD',
            ],
            [
                'path' => 'queue.connections.rabbitmq.management.vhost',
                'env' => 'RABBITMQ_MANAGEMENT_VHOST',
            ],
            [
                'path' => 'processing.openai.api_key',
                'env' => 'OPENAI_API_KEY',
            ],
        ],
    ],
    'classification_queues' => [
        'contract' => 'queue.classify.contract',
        'tax' => 'queue.classify.tax',
        'invoice' => 'queue.classify.invoice',
        'general' => 'queue.classify.general',
    ],
];
