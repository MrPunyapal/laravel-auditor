<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LaravelAuditor\Context\Snapshots\SchemaComparator;
use LaravelAuditor\Context\Snapshots\SnapshotBuilder;
use LaravelAuditor\Context\Snapshots\SnapshotComparator;

it('captures schema shape only when explicitly requested', function () {
    $builder = app(SnapshotBuilder::class);

    expect($builder->capture()['sections'])->not->toHaveKey('schema');

    Schema::create('auditor_snapshot_table', function (Blueprint $table): void {
        $table->id();
        $table->string('status')->default('private-token');
    });

    try {
        $snapshot = $builder->capture(withSchema: true);
        $tables = $snapshot['sections']['schema']['tables'];
        $table = collect($tables)->firstWhere('name', 'auditor_snapshot_table');

        expect($table)->toBeArray()
            ->and($snapshot['sections']['schema'])->not->toHaveKeys(['database', 'connection'])
            ->and($table['columns'][1])->not->toHaveKey('default');
    } finally {
        Schema::dropIfExists('auditor_snapshot_table');
    }
});

it('reports changed schema structures without claiming an executed migration', function () {
    $table = ['name' => 'users', 'columns' => [
        ['name' => 'id', 'type' => 'integer', 'nullable' => false],
    ], 'indexes' => [], 'foreign_keys' => []];

    $old = ['driver' => 'sqlite', 'tables' => [$table]];
    $new = $old;
    $new['tables'][0]['columns'][] = ['name' => 'email', 'type' => 'varchar', 'nullable' => false];

    $diff = (new SchemaComparator)->compare($old, $new);

    expect($diff['added'])->toBe([])
        ->and($diff['removed'])->toBe([])
        ->and($diff['modified'])->toHaveCount(1)
        ->and($diff['modified'][0]['table'])->toBe('users');
});

it('refuses incomplete schema comparisons', function () {
    $base = app(SnapshotBuilder::class)->capture();
    $before = $base;
    $before['sections']['schema'] = ['driver' => 'sqlite', 'tables' => []];

    expect(fn () => app(SnapshotComparator::class)->compare($before, $base))
        ->toThrow(RuntimeException::class);

    expect(fn () => (new SchemaComparator)->compare(
        ['driver' => 'mysql', 'tables' => []],
        ['driver' => 'pgsql', 'tables' => []],
    ))->toThrow(RuntimeException::class);
});
