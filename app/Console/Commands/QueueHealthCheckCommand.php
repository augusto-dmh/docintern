<?php

namespace App\Console\Commands;

use App\Services\RabbitMqQueueHealthService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class QueueHealthCheckCommand extends Command
{
    protected $signature = 'docintern:queue-health-check';

    protected $description = 'Validate RabbitMQ management queue health connectivity';

    public function __construct(
        public RabbitMqQueueHealthService $rabbitMqQueueHealthService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $snapshot = $this->rabbitMqQueueHealthService->snapshot();

        if (($snapshot['available'] ?? false) !== true) {
            $this->error('Queue health check failed.');
            $this->line((string) ($snapshot['error'] ?? 'Queue health metrics are currently unavailable.'));

            return SymfonyCommand::FAILURE;
        }

        $summary = is_array($snapshot['summary'] ?? null)
            ? $snapshot['summary']
            : [];

        $this->info('Queue health check passed.');
        $this->line('Total messages: '.(int) ($summary['total_messages'] ?? 0));
        $this->line('Dead letter messages: '.(int) ($summary['dead_letter_messages'] ?? 0));

        return SymfonyCommand::SUCCESS;
    }
}
