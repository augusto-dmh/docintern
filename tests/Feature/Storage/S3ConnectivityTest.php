<?php

use Illuminate\Support\Facades\Storage;

test('s3 disk is configured for private storage and throws exceptions', function () {
    expect(config('filesystems.disks.s3.driver'))->toBe('s3')
        ->and(config('filesystems.disks.s3.visibility'))->toBe('private')
        ->and(config('filesystems.disks.s3.throw'))->toBeTrue();
});

test('s3 disk supports writing and reading when faked', function () {
    Storage::fake('s3');

    $filePath = 'tenants/demo/documents/1/example.txt';

    Storage::disk('s3')->put($filePath, 'document body');

    Storage::disk('s3')->assertExists($filePath);
    expect(Storage::disk('s3')->get($filePath))->toBe('document body');
});
