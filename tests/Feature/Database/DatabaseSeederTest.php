<?php

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Matter;
use Database\Seeders\DatabaseSeeder;

afterEach(function () {
    tenancy()->end();
});

test('database seeder builds document-rich demo data for phase 2 walkthroughs', function () {
    $this->seed(DatabaseSeeder::class);

    $statuses = Document::query()
        ->pluck('status')
        ->unique()
        ->all();

    expect(Matter::query()->count())->toBe(15)
        ->and(Document::query()->count())->toBeGreaterThan(0)
        ->and(AuditLog::query()->count())->toBeGreaterThanOrEqual(Document::query()->count())
        ->and(Matter::query()->whereDoesntHave('documents')->exists())->toBeTrue()
        ->and(Matter::query()->has('documents')->exists())->toBeTrue()
        ->and($statuses)->toContain('uploaded')
        ->and($statuses)->toContain('ready_for_review')
        ->and($statuses)->toContain('approved');
});
