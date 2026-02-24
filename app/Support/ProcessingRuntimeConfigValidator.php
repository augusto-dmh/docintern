<?php

namespace App\Support;

use InvalidArgumentException;

class ProcessingRuntimeConfigValidator
{
    public function validateOrFail(): void
    {
        $providerMode = $this->providerMode();
        $supportedProviderModes = $this->supportedProviderModes();

        if (! in_array($providerMode, $supportedProviderModes, true)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported processing provider mode [%s]. Supported modes: %s.',
                $providerMode,
                implode(', ', $supportedProviderModes),
            ));
        }

        if ($providerMode !== 'live') {
            return;
        }

        $issues = [
            ...$this->exactContractIssues(),
            ...$this->nonEmptyContractIssues(),
        ];

        if ($issues === []) {
            return;
        }

        throw new InvalidArgumentException(
            "Live provider mode configuration is invalid:\n- ".implode("\n- ", $issues),
        );
    }

    protected function providerMode(): string
    {
        return strtolower(trim((string) config('processing.provider_mode', 'simulated')));
    }

    /**
     * @return list<string>
     */
    protected function supportedProviderModes(): array
    {
        $supportedModes = config('processing.supported_provider_modes', ['simulated', 'live']);

        if (! is_array($supportedModes)) {
            return ['simulated', 'live'];
        }

        $normalizedModes = array_values(array_filter(
            array_map(
                static fn (mixed $value): string => strtolower(trim((string) $value)),
                $supportedModes,
            ),
            static fn (string $value): bool => $value !== '',
        ));

        return $normalizedModes === [] ? ['simulated', 'live'] : $normalizedModes;
    }

    /**
     * @return list<string>
     */
    protected function exactContractIssues(): array
    {
        $issues = [];
        $exactContracts = config('processing.live_required_contract.exact', []);

        if (! is_array($exactContracts)) {
            return $issues;
        }

        foreach ($exactContracts as $contract) {
            if (! is_array($contract)) {
                continue;
            }

            $configPath = trim((string) ($contract['path'] ?? ''));
            $envKey = trim((string) ($contract['env'] ?? ''));
            $expected = strtolower(trim((string) ($contract['expected'] ?? '')));

            if ($configPath === '' || $envKey === '' || $expected === '') {
                continue;
            }

            $actual = strtolower(trim((string) config($configPath)));

            if ($actual !== $expected) {
                $issues[] = sprintf('%s must be set to [%s].', $envKey, $expected);
            }
        }

        return $issues;
    }

    /**
     * @return list<string>
     */
    protected function nonEmptyContractIssues(): array
    {
        $issues = [];
        $requiredContracts = config('processing.live_required_contract.non_empty', []);

        if (! is_array($requiredContracts)) {
            return $issues;
        }

        foreach ($requiredContracts as $contract) {
            if (! is_array($contract)) {
                continue;
            }

            $configPath = trim((string) ($contract['path'] ?? ''));
            $envKey = trim((string) ($contract['env'] ?? ''));

            if ($configPath === '' || $envKey === '') {
                continue;
            }

            $value = config($configPath);

            if (is_string($value) && trim($value) !== '') {
                continue;
            }

            if (is_int($value) || is_float($value)) {
                continue;
            }

            $issues[] = sprintf('%s must be set for live mode.', $envKey);
        }

        return $issues;
    }
}
