<?php

test('rabbitmq definitions file is valid json and has required topology sections', function () {
    $content = file_get_contents(base_path('docker/rabbitmq/definitions.json'));

    expect($content)->not->toBeFalse();

    try {
        /** @var array<string, mixed> $definition */
        $definition = json_decode((string) $content, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        $this->fail('definitions.json is invalid JSON: '.$exception->getMessage());
    }

    expect($definition)->toHaveKeys(['vhosts', 'permissions', 'exchanges', 'queues', 'bindings'])
        ->and($definition['exchanges'])->toBeArray()
        ->and($definition['queues'])->toBeArray()
        ->and($definition['bindings'])->toBeArray();
});

test('rabbitmq definitions include expected exchanges queues and bindings', function () {
    /** @var array{
     *     exchanges: array<int, array{name: string}>,
     *     queues: array<int, array{name: string, arguments: array<string, string>}>,
     *     bindings: array<int, array{source: string, destination: string, routing_key: string}>
     * } $definition
     */
    $definition = json_decode((string) file_get_contents(base_path('docker/rabbitmq/definitions.json')), true, 512, JSON_THROW_ON_ERROR);

    $exchangeNames = collect($definition['exchanges'])
        ->pluck('name')
        ->all();

    $queueNames = collect($definition['queues'])
        ->pluck('name')
        ->all();

    expect($exchangeNames)->toContain('docintern.upload')
        ->and($exchangeNames)->toContain('docintern.processing')
        ->and($exchangeNames)->toContain('docintern.notifications')
        ->and($exchangeNames)->toContain('docintern.dlx')
        ->and($queueNames)->toContain('queue.virus-scan')
        ->and($queueNames)->toContain('queue.audit-log')
        ->and($queueNames)->toContain('queue.ocr-extraction')
        ->and($queueNames)->toContain('queue.classify.contract')
        ->and($queueNames)->toContain('queue.classify.tax')
        ->and($queueNames)->toContain('queue.classify.invoice')
        ->and($queueNames)->toContain('queue.classify.general')
        ->and($queueNames)->toContain('queue.notify.email')
        ->and($queueNames)->toContain('queue.notify.inapp')
        ->and($queueNames)->toContain('queue.dead-letters');

    expect(collect($definition['bindings'])->contains(function (array $binding): bool {
        return $binding['source'] === 'docintern.dlx'
            && $binding['destination'] === 'queue.dead-letters'
            && $binding['routing_key'] === '#';
    }))->toBeTrue();
});

test('rabbitmq primary queues define dead letter exchange arguments', function () {
    /** @var array{queues: array<int, array{name: string, arguments: array<string, string>}>} $definition */
    $definition = json_decode((string) file_get_contents(base_path('docker/rabbitmq/definitions.json')), true, 512, JSON_THROW_ON_ERROR);

    $primaryQueues = [
        'queue.virus-scan',
        'queue.audit-log',
        'queue.ocr-extraction',
        'queue.classify.contract',
        'queue.classify.tax',
        'queue.classify.invoice',
        'queue.classify.general',
        'queue.notify.email',
        'queue.notify.inapp',
    ];

    foreach ($primaryQueues as $queueName) {
        $queue = collect($definition['queues'])->firstWhere('name', $queueName);

        expect($queue)->toBeArray()
            ->and($queue)->toHaveKey('arguments')
            ->and($queue['arguments'])->toHaveKey('x-dead-letter-exchange')
            ->and($queue['arguments']['x-dead-letter-exchange'])->toBe('docintern.dlx')
            ->and($queue['arguments'])->toHaveKey('x-dead-letter-routing-key');
    }
});
