<?php

use App\Services\RabbitMqQueueHealthService;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

test('queue health check command succeeds when snapshot is available', function (): void {
    app()->instance(RabbitMqQueueHealthService::class, new class extends RabbitMqQueueHealthService
    {
        public function snapshot(): array
        {
            return [
                'available' => true,
                'generated_at' => now()->toISOString(),
                'queues' => [],
                'summary' => [
                    'total_messages' => 5,
                    'total_ready' => 2,
                    'total_unacked' => 3,
                    'total_consumers' => 2,
                    'dead_letter_messages' => 1,
                ],
                'error' => null,
            ];
        }
    });

    $this->artisan('docintern:queue-health-check')
        ->expectsOutputToContain('Queue health check passed.')
        ->expectsOutputToContain('Total messages: 5')
        ->expectsOutputToContain('Dead letter messages: 1')
        ->assertExitCode(SymfonyCommand::SUCCESS);
});

test('queue health check command fails when snapshot is unavailable', function (): void {
    app()->instance(RabbitMqQueueHealthService::class, new class extends RabbitMqQueueHealthService
    {
        public function snapshot(): array
        {
            return [
                'available' => false,
                'generated_at' => now()->toISOString(),
                'queues' => [],
                'summary' => [
                    'total_messages' => 0,
                    'total_ready' => 0,
                    'total_unacked' => 0,
                    'total_consumers' => 0,
                    'dead_letter_messages' => 0,
                ],
                'error' => 'Queue health metrics are currently unavailable.',
            ];
        }
    });

    $this->artisan('docintern:queue-health-check')
        ->expectsOutputToContain('Queue health check failed.')
        ->expectsOutputToContain('Queue health metrics are currently unavailable.')
        ->assertExitCode(SymfonyCommand::FAILURE);
});
