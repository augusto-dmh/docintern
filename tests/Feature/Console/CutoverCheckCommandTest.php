<?php

use Symfony\Component\Console\Command\Command as SymfonyCommand;

test('cutover check command passes in simulated mode', function (): void {
    config()->set('processing.provider_mode', 'simulated');

    $this->artisan('docintern:cutover-check')
        ->expectsOutputToContain('Provider mode: simulated')
        ->expectsOutputToContain('Cutover contract check passed for simulated provider mode.')
        ->assertExitCode(SymfonyCommand::SUCCESS);
});

test('cutover check command fails when live contracts are invalid', function (): void {
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
    config()->set('processing.openai.api_key', '');

    $this->artisan('docintern:cutover-check')
        ->expectsOutputToContain('Provider mode: live')
        ->expectsOutputToContain('Cutover contract check failed.')
        ->expectsOutputToContain('PROCESSING_OCR_PROVIDER must be set to [live].')
        ->assertExitCode(SymfonyCommand::FAILURE);
});

test('cutover check command fails when openai live contract is missing', function (): void {
    config()->set('processing.provider_mode', 'live');
    config()->set('processing.ocr_provider', 'live');
    config()->set('processing.classification_provider', 'live');
    config()->set('filesystems.default', 's3');
    config()->set('processing.queue_connection', 'rabbitmq');
    config()->set('aws.region', 'us-east-1');
    config()->set('filesystems.disks.s3.bucket', 'docintern-production');
    config()->set('queue.connections.rabbitmq.management.host', 'rabbitmq.example.com');
    config()->set('queue.connections.rabbitmq.management.username', 'docintern');
    config()->set('queue.connections.rabbitmq.management.password', 'secret');
    config()->set('queue.connections.rabbitmq.management.vhost', '/docintern');
    config()->set('processing.openai.api_key', '');

    $this->artisan('docintern:cutover-check')
        ->expectsOutputToContain('Provider mode: live')
        ->expectsOutputToContain('Cutover contract check failed.')
        ->expectsOutputToContain('OPENAI_API_KEY must be set for live mode.')
        ->assertExitCode(SymfonyCommand::FAILURE);
});
