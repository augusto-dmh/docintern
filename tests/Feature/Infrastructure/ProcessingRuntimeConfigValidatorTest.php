<?php

use App\Support\ProcessingRuntimeConfigValidator;

test('runtime validator reports missing required unified development keys', function (): void {
    config()->set('processing.ocr_provider', 'simulated');
    config()->set('processing.classification_provider', 'simulated');
    config()->set('filesystems.default', 'local');
    config()->set('processing.queue_connection', 'sync');
    config()->set('aws.credentials.key', '');
    config()->set('aws.credentials.secret', '');
    config()->set('aws.region', '');
    config()->set('filesystems.disks.s3.bucket', '');
    config()->set('processing.openai.api_key', '');

    try {
        app(ProcessingRuntimeConfigValidator::class)->validateOrFail();
        $this->fail('Expected runtime validation to fail.');
    } catch (\InvalidArgumentException $exception) {
        expect($exception->getMessage())
            ->toContain('PROCESSING_OCR_PROVIDER must be set to [openai].')
            ->toContain('PROCESSING_CLASSIFICATION_PROVIDER must be set to [openai].')
            ->toContain('FILESYSTEM_DISK must be set to [s3].')
            ->toContain('PROCESSING_QUEUE_CONNECTION must be set to [rabbitmq].')
            ->toContain('AWS_ACCESS_KEY_ID must be set for development runtime.')
            ->toContain('AWS_SECRET_ACCESS_KEY must be set for development runtime.')
            ->toContain('AWS_BUCKET must be set for development runtime.')
            ->toContain('OPENAI_API_KEY must be set for development runtime.');
    }
});

test('runtime validator fails when providers are not configured as openai', function (): void {
    configureRuntimeContractsForValidation();

    config()->set('processing.ocr_provider', 'simulated');
    config()->set('processing.classification_provider', 'simulated');

    try {
        app(ProcessingRuntimeConfigValidator::class)->validateOrFail();
        $this->fail('Expected provider configuration validation to fail.');
    } catch (\InvalidArgumentException $exception) {
        expect($exception->getMessage())
            ->toContain('PROCESSING_OCR_PROVIDER must be set to [openai].')
            ->toContain('PROCESSING_CLASSIFICATION_PROVIDER must be set to [openai].');
    }
});

function configureRuntimeContractsForValidation(): void
{
    config()->set('processing.ocr_provider', 'openai');
    config()->set('processing.classification_provider', 'openai');
    config()->set('processing.queue_connection', 'rabbitmq');
    config()->set('filesystems.default', 's3');
    config()->set('aws.credentials.key', 'aws-key');
    config()->set('aws.credentials.secret', 'aws-secret');
    config()->set('aws.region', 'us-east-1');
    config()->set('filesystems.disks.s3.bucket', 'docintern-production');
    config()->set('processing.openai.api_key', 'test-openai-key');
}
