<?php

use App\Support\ProcessingRuntimeConfigValidator;

test('simulated provider mode passes validation without live contracts', function (): void {
    config()->set('processing.provider_mode', 'simulated');

    app(ProcessingRuntimeConfigValidator::class)->validateOrFail();

    expect(true)->toBeTrue();
});

test('live provider mode reports missing required configuration keys', function (): void {
    config()->set('processing.provider_mode', 'live');
    config()->set('processing.ocr_provider', 'simulated');
    config()->set('processing.classification_provider', 'simulated');
    config()->set('filesystems.default', 'local');
    config()->set('processing.queue_connection', 'sync');
    config()->set('aws.region', '');
    config()->set('filesystems.disks.s3.bucket', '');
    config()->set('queue.connections.rabbitmq.management.host', '');
    config()->set('queue.connections.rabbitmq.management.username', '');
    config()->set('queue.connections.rabbitmq.management.password', '');
    config()->set('queue.connections.rabbitmq.management.vhost', '');
    config()->set('processing.comprehend.endpoint_arn', '');

    try {
        app(ProcessingRuntimeConfigValidator::class)->validateOrFail();
        $this->fail('Expected live mode validation to fail.');
    } catch (\InvalidArgumentException $exception) {
        expect($exception->getMessage())
            ->toContain('PROCESSING_OCR_PROVIDER must be set to [live].')
            ->toContain('PROCESSING_CLASSIFICATION_PROVIDER must be set to [live].')
            ->toContain('FILESYSTEM_DISK must be set to [s3].')
            ->toContain('PROCESSING_QUEUE_CONNECTION must be set to [rabbitmq].')
            ->toContain('AWS_BUCKET must be set for live mode.')
            ->toContain('RABBITMQ_MANAGEMENT_PASSWORD must be set for live mode.')
            ->toContain('PROCESSING_COMPREHEND_ENDPOINT_ARN must be set for live mode.');
    }
});

test('live provider mode fails when providers are not configured as live', function (): void {
    configureLiveModeContractsForValidation();

    config()->set('processing.ocr_provider', 'simulated');
    config()->set('processing.classification_provider', 'simulated');

    try {
        app(ProcessingRuntimeConfigValidator::class)->validateOrFail();
        $this->fail('Expected provider configuration validation to fail.');
    } catch (\InvalidArgumentException $exception) {
        expect($exception->getMessage())
            ->toContain('PROCESSING_OCR_PROVIDER must be set to [live].')
            ->toContain('PROCESSING_CLASSIFICATION_PROVIDER must be set to [live].');
    }
});

function configureLiveModeContractsForValidation(): void
{
    config()->set('processing.provider_mode', 'live');
    config()->set('processing.ocr_provider', 'live');
    config()->set('processing.classification_provider', 'live');
    config()->set('processing.queue_connection', 'rabbitmq');
    config()->set('filesystems.default', 's3');
    config()->set('aws.region', 'us-east-1');
    config()->set('filesystems.disks.s3.bucket', 'docintern-production');
    config()->set('queue.connections.rabbitmq.management.host', 'rabbitmq.example.com');
    config()->set('queue.connections.rabbitmq.management.username', 'docintern');
    config()->set('queue.connections.rabbitmq.management.password', 'secret');
    config()->set('queue.connections.rabbitmq.management.vhost', '/docintern');
    config()->set('processing.comprehend.endpoint_arn', 'arn:aws:comprehend:us-east-1:123456789012:document-classifier-endpoint/docintern');
}
