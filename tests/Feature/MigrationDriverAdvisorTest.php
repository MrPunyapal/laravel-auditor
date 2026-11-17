<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LaravelAuditor\Context\Migrations\MigrationDriverAdvisor;
use LaravelAuditor\Context\Snapshots\SnapshotBuilder;

it('returns conditional driver-specific guidance without lock or version guarantees', function () {
    $report = [
        'driver' => 'pgsql',
        'indicators' => [[
            'code' => 'index_added', 'table' => 'events', 'schema' => 'public', 'column' => null,
        ], [
            'code' => 'foreign_key_added', 'table' => 'events', 'schema' => 'public', 'column' => null,
        ]],
    ];

    $result = (new MigrationDriverAdvisor)->review($report);

    expect($result['supported_guidance'])->toBeTrue()
        ->and($result['assessment'])->toBe('conditional_review_only')
        ->and($result['database_version_verified'])->toBeFalse()
        ->and($result['locking_verified'])->toBeFalse()
        ->and($result['questions'])->toHaveCount(2)
        ->and($result['questions'][0]['check'])->toContain('CONCURRENTLY')
        ->and($result['questions'][1]['check'])->toContain('NOT VALID');
});

it('keeps MySQL, MariaDB, PostgreSQL and SQLite guidance distinct', function (string $driver, string $keyword) {
    $review = (new MigrationDriverAdvisor)->review([
        'driver' => $driver,
        'indicators' => [['code' => 'column_type_changed', 'table' => 'orders', 'schema' => null, 'column' => 'amount']],
    ]);

    expect($review['supported_guidance'])->toBeTrue()
        ->and($review['questions'][0]['check'])->toContain($keyword);
})->with([
    'mysql' => ['mysql', 'metadata locks'],
    'mariadb' => ['mariadb', 'ALGORITHM/LOCK'],
    'pgsql' => ['pgsql', 'ALTER COLUMN TYPE'],
    'sqlite' => ['sqlite', 'rebuild'],
]);

it('does not invent driver guidance for unsupported drivers', function () {
    $review = (new MigrationDriverAdvisor)->review([
        'driver' => 'sqlsrv', 'indicators' => [['code' => 'index_added']],
    ]);

    expect($review['supported_guidance'])->toBeFalse()
        ->and($review['questions'])->toBe([])
        ->and($review['locking_verified'])->toBeFalse();
});

it('exposes driver guidance only when explicitly requested by the Artisan command', function () {
    $beforePath = sys_get_temp_dir().'/auditor-driver-before-'.uniqid().'.json';
    $afterPath = sys_get_temp_dir().'/auditor-driver-after-'.uniqid().'.json';
    $snapshot = app(SnapshotBuilder::class)->capture();
    $snapshot['sections']['schema'] = ['driver' => 'pgsql', 'tables' => [[
        'name' => 'events', 'schema' => 'public',
        'columns' => [['name' => 'id', 'type' => 'integer', 'nullable' => false]],
        'indexes' => [], 'foreign_keys' => [],
    ]]];
    $after = $snapshot;
    $after['sections']['schema']['tables'][0]['indexes'][] = [
        'name' => 'event_id_idx', 'columns' => ['id'], 'unique' => false,
    ];

    try {
        file_put_contents($beforePath, json_encode($snapshot, JSON_THROW_ON_ERROR));
        file_put_contents($afterPath, json_encode($after, JSON_THROW_ON_ERROR));
        expect(Artisan::call('auditor:migration-risk', [
            'before' => $beforePath, 'after' => $afterPath,
        ]))->toBe(0);
        expect(json_decode(Artisan::output(), true))->not->toHaveKey('driver_review');

        expect(Artisan::call('auditor:migration-risk', [
            'before' => $beforePath, 'after' => $afterPath, '--driver-guidance' => true,
        ]))->toBe(0);

        $result = json_decode(Artisan::output(), true);

        expect($result['driver_review']['questions'])->toHaveCount(1)
            ->and($result['driver_review']['locking_verified'])->toBeFalse();
    } finally {
        foreach ([$beforePath, $afterPath] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
});
