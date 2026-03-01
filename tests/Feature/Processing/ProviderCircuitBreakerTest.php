<?php

use App\Services\Processing\ProviderCircuitBreaker;
use App\Services\Processing\ProviderDegradedException;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::flush();
});

test('provider circuit breaker opens after repeated transient failures', function (): void {
    config()->set('processing.provider_circuit.failure_threshold', 2);
    config()->set('processing.provider_circuit.cooldown_seconds', 60);

    /** @var ProviderCircuitBreaker $breaker */
    $breaker = app(ProviderCircuitBreaker::class);

    $operationCallCount = 0;
    $failingOperation = function () use (&$operationCallCount): void {
        $operationCallCount++;

        throw new RuntimeException('upstream timeout');
    };
    $transientMatcher = fn (): bool => true;

    expect(fn () => $breaker->run('textract', $failingOperation, $transientMatcher))
        ->toThrow(ProviderDegradedException::class, 'upstream timeout');
    expect(fn () => $breaker->run('textract', $failingOperation, $transientMatcher))
        ->toThrow(ProviderDegradedException::class, 'upstream timeout');

    expect(fn () => $breaker->run('textract', function () use (&$operationCallCount): string {
        $operationCallCount++;

        return 'ok';
    }, $transientMatcher))->toThrow(ProviderDegradedException::class, 'circuit_breaker_open');

    expect($operationCallCount)->toBe(2);
});

test('provider circuit breaker allows execution after cooldown window', function (): void {
    config()->set('processing.provider_circuit.failure_threshold', 1);
    config()->set('processing.provider_circuit.cooldown_seconds', 1);

    /** @var ProviderCircuitBreaker $breaker */
    $breaker = app(ProviderCircuitBreaker::class);
    $transientMatcher = fn (): bool => true;

    expect(fn () => $breaker->run('openai', function (): void {
        throw new RuntimeException('service unavailable');
    }, $transientMatcher))->toThrow(ProviderDegradedException::class);

    expect(fn () => $breaker->run('openai', fn () => 'nope', $transientMatcher))
        ->toThrow(ProviderDegradedException::class, 'circuit_breaker_open');

    $this->travel(2)->seconds();

    $result = $breaker->run('openai', fn () => 'ok', $transientMatcher);

    expect($result)->toBe('ok');
});
