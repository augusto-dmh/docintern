<?php

use Symfony\Component\Console\Command\Command as SymfonyCommand;

test('setup rabbitmq command validates topology in dry run mode', function () {
    $this->artisan('docintern:setup-rabbitmq', [
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('RabbitMQ topology definition loaded.')
        ->expectsOutputToContain('Path: '.base_path('docker/rabbitmq/definitions.json'))
        ->expectsOutputToContain('Exchanges: 4')
        ->expectsOutputToContain('Queues: 10')
        ->expectsOutputToContain('Bindings: 10')
        ->expectsOutputToContain('Dry run enabled. No RabbitMQ changes were applied.')
        ->assertExitCode(SymfonyCommand::SUCCESS);
});

test('setup rabbitmq command is idempotent across repeated runs', function () {
    $firstRun = $this->artisan('docintern:setup-rabbitmq', [
        '--dry-run' => true,
    ])->assertExitCode(SymfonyCommand::SUCCESS);

    $secondRun = $this->artisan('docintern:setup-rabbitmq', [
        '--dry-run' => true,
    ])->assertExitCode(SymfonyCommand::SUCCESS);

    expect($firstRun)->not->toBeNull()
        ->and($secondRun)->not->toBeNull();
});
