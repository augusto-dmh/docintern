<?php

namespace App\Console\Commands;

use App\Support\ProcessingRuntimeConfigValidator;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class CutoverCheckCommand extends Command
{
    protected $signature = 'docintern:cutover-check';

    protected $description = 'Validate live infrastructure cutover configuration contracts';

    public function __construct(
        public ProcessingRuntimeConfigValidator $processingRuntimeConfigValidator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $providerMode = strtolower(trim((string) config('processing.provider_mode', 'simulated')));
        $this->line(sprintf('Provider mode: %s', $providerMode));

        try {
            $this->processingRuntimeConfigValidator->validateOrFail();
        } catch (InvalidArgumentException $exception) {
            $this->error('Cutover contract check failed.');
            $this->line($exception->getMessage());

            return SymfonyCommand::FAILURE;
        }

        if ($providerMode === 'live') {
            $this->info('Cutover contract check passed for live provider mode.');

            return SymfonyCommand::SUCCESS;
        }

        $this->info('Cutover contract check passed for simulated provider mode.');

        return SymfonyCommand::SUCCESS;
    }
}
