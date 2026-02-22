<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use JsonException;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class SetupRabbitMqCommand extends Command
{
    protected $signature = 'docintern:setup-rabbitmq
        {--dry-run : Validate topology definitions and print a summary without applying changes}';

    protected $description = 'Validate and bootstrap RabbitMQ topology definitions';

    public function handle(): int
    {
        $definitionPath = base_path('docker/rabbitmq/definitions.json');

        if (! is_file($definitionPath)) {
            $this->error("RabbitMQ definitions file was not found at [{$definitionPath}].");

            return SymfonyCommand::FAILURE;
        }

        try {
            $definition = $this->readDefinitionFile($definitionPath);
        } catch (JsonException $exception) {
            $this->error('RabbitMQ definitions file contains invalid JSON.');
            $this->line($exception->getMessage());

            return SymfonyCommand::FAILURE;
        }

        if (! $this->hasRequiredDefinitionShape($definition)) {
            $this->error('RabbitMQ definitions file is missing one or more required sections.');
            $this->line('Required sections: vhosts, permissions, exchanges, queues, bindings');

            return SymfonyCommand::FAILURE;
        }

        $this->line('RabbitMQ topology definition loaded.');
        $this->line('Path: '.$definitionPath);
        $this->line('Vhosts: '.count($definition['vhosts']));
        $this->line('Permissions: '.count($definition['permissions']));
        $this->line('Exchanges: '.count($definition['exchanges']));
        $this->line('Queues: '.count($definition['queues']));
        $this->line('Bindings: '.count($definition['bindings']));

        if ((bool) $this->option('dry-run')) {
            $this->line('Dry run enabled. No RabbitMQ changes were applied.');

            return SymfonyCommand::SUCCESS;
        }

        $this->line('Topology bootstrap skeleton executed (no-op).');
        $this->line('Definitions remain the source of truth via docker/rabbitmq/definitions.json.');

        return SymfonyCommand::SUCCESS;
    }

    /**
     * @return array{
     *     vhosts: array<int, mixed>,
     *     permissions: array<int, mixed>,
     *     exchanges: array<int, mixed>,
     *     queues: array<int, mixed>,
     *     bindings: array<int, mixed>
     * }
     *
     * @throws JsonException
     */
    protected function readDefinitionFile(string $definitionPath): array
    {
        $content = file_get_contents($definitionPath);

        if ($content === false) {
            throw new JsonException('Unable to read RabbitMQ definitions file.');
        }

        /** @var array{
         *     vhosts: array<int, mixed>,
         *     permissions: array<int, mixed>,
         *     exchanges: array<int, mixed>,
         *     queues: array<int, mixed>,
         *     bindings: array<int, mixed>
         * } $decoded
         */
        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    protected function hasRequiredDefinitionShape(array $definition): bool
    {
        foreach (['vhosts', 'permissions', 'exchanges', 'queues', 'bindings'] as $requiredKey) {
            if (! array_key_exists($requiredKey, $definition) || ! is_array($definition[$requiredKey])) {
                return false;
            }
        }

        return true;
    }
}
