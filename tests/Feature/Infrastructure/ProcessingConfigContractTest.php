<?php

test('processing provider mode defaults to simulated', function (): void {
    withTemporaryEnvironment([
        'DOCINTERN_PROVIDER_MODE' => null,
        'PROCESSING_PROVIDER_MODE' => null,
        'PROCESSING_OCR_PROVIDER' => null,
        'PROCESSING_CLASSIFICATION_PROVIDER' => null,
    ], function (): void {
        /** @var array{provider_mode: string} $processingConfig */
        $processingConfig = require base_path('config/processing.php');

        expect($processingConfig['provider_mode'])->toBe('simulated');
    });
});

test('processing provider mode derives live when both providers are live', function (): void {
    withTemporaryEnvironment([
        'DOCINTERN_PROVIDER_MODE' => null,
        'PROCESSING_PROVIDER_MODE' => null,
        'PROCESSING_OCR_PROVIDER' => 'live',
        'PROCESSING_CLASSIFICATION_PROVIDER' => 'live',
    ], function (): void {
        /** @var array{provider_mode: string} $processingConfig */
        $processingConfig = require base_path('config/processing.php');

        expect($processingConfig['provider_mode'])->toBe('live');
    });
});

test('processing provider circuit defaults are defined', function (): void {
    withTemporaryEnvironment([
        'PROCESSING_PROVIDER_CIRCUIT_FAILURE_THRESHOLD' => null,
        'PROCESSING_PROVIDER_CIRCUIT_COOLDOWN_SECONDS' => null,
        'PROCESSING_PROVIDER_DEGRADED_REQUEUE_DELAY_SECONDS' => null,
    ], function (): void {
        /** @var array{
         *     provider_circuit: array{failure_threshold: int, cooldown_seconds: int},
         *     provider_degraded_requeue_delay_seconds: int
         * } $processingConfig
         */
        $processingConfig = require base_path('config/processing.php');

        expect($processingConfig['provider_circuit']['failure_threshold'])->toBe(3)
            ->and($processingConfig['provider_circuit']['cooldown_seconds'])->toBe(60)
            ->and($processingConfig['provider_degraded_requeue_delay_seconds'])->toBe(30);
    });
});

test('aws endpoint url takes precedence over legacy aws endpoint', function (): void {
    withTemporaryEnvironment([
        'AWS_ENDPOINT_URL' => 'http://canonical-endpoint.test',
        'AWS_ENDPOINT' => 'http://legacy-endpoint.test',
    ], function (): void {
        /** @var array{endpoint: string|null} $awsConfig */
        $awsConfig = require base_path('config/aws.php');
        /** @var array{disks: array<string, array<string, mixed>>} $filesystemConfig */
        $filesystemConfig = require base_path('config/filesystems.php');

        expect($awsConfig['endpoint'])->toBe('http://canonical-endpoint.test')
            ->and($filesystemConfig['disks']['s3']['endpoint'])->toBe('http://canonical-endpoint.test');
    });
});

test('aws endpoint falls back to legacy aws endpoint when canonical endpoint is missing', function (): void {
    withTemporaryEnvironment([
        'AWS_ENDPOINT_URL' => null,
        'AWS_ENDPOINT' => 'http://legacy-endpoint.test',
    ], function (): void {
        /** @var array{endpoint: string|null} $awsConfig */
        $awsConfig = require base_path('config/aws.php');
        /** @var array{disks: array<string, array<string, mixed>>} $filesystemConfig */
        $filesystemConfig = require base_path('config/filesystems.php');

        expect($awsConfig['endpoint'])->toBe('http://legacy-endpoint.test')
            ->and($filesystemConfig['disks']['s3']['endpoint'])->toBe('http://legacy-endpoint.test');
    });
});

/**
 * @param  array<string, string|null>  $overrides
 * @param  callable(): void  $callback
 */
function withTemporaryEnvironment(array $overrides, callable $callback): void
{
    /** @var array<string, string|false> $originalValues */
    $originalValues = [];

    foreach ($overrides as $key => $value) {
        $originalValues[$key] = getenv($key);

        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            continue;
        }

        putenv(sprintf('%s=%s', $key, $value));
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    try {
        $callback();
    } finally {
        foreach ($originalValues as $key => $value) {
            if ($value === false) {
                putenv($key);
                unset($_ENV[$key], $_SERVER[$key]);

                continue;
            }

            putenv(sprintf('%s=%s', $key, $value));
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}
