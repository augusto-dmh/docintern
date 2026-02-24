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

    $this->artisan('docintern:cutover-check')
        ->expectsOutputToContain('Provider mode: live')
        ->expectsOutputToContain('Cutover contract check failed.')
        ->expectsOutputToContain('PROCESSING_OCR_PROVIDER must be set to [live].')
        ->assertExitCode(SymfonyCommand::FAILURE);
});
