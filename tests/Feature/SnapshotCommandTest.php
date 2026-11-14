<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LaravelAuditor\Context\Snapshots\SnapshotBuilder;

it('captures a versioned, deterministic snapshot without timestamps or database credentials', function () {
    $first = app(SnapshotBuilder::class)->capture();
    $second = app(SnapshotBuilder::class)->capture();

    expect($first)->toEqual($second)
        ->and($first['format'])->toBe(SnapshotBuilder::FORMAT)
        ->and($first['version'])->toBe(1)
        ->and($first['sections'])->toHaveKeys(['routes', 'authorization'])
        ->and($first['sections']['routes'])->not->toBeEmpty()
        ->and($first['sections']['authorization'])->toHaveKeys(['gates', 'policies', 'policy_files', 'middleware_aliases'])
        ->and($first)->not->toHaveKeys(['generated_at', 'database']);
});

it('exposes snapshot JSON via Artisan and writes it to a file', function () {
    $path = sys_get_temp_dir().'/laravel-auditor-snapshot-'.uniqid().'.json';

    try {
        expect(Artisan::call('auditor:snapshot'))->toBe(0);
        $stdout = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->artisan('auditor:snapshot', ['--output' => $path])
            ->expectsOutputToContain('Snapshot written')
            ->assertSuccessful();

        $saved = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        expect($saved)->toEqual($stdout);
    } finally {
        if (file_exists($path)) {
            unlink($path);
        }
    }
});
