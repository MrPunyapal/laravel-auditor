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

it('records SQLite index column order instead of silently omitting the indexed columns', function () {
    Schema::create('auditor_index_columns', function (Blueprint $table): void {
        $table->id();
        $table->string('status');
        $table->string('category');
        $table->index(['status', 'category'], 'auditor_snapshot_compound_idx');
    });

    try {
        $snapshot = app(SnapshotBuilder::class)->capture(withSchema: true);
        $table = collect($snapshot['sections']['schema']['tables'])->firstWhere('name', 'auditor_index_columns');
        $index = collect($table['indexes'])->firstWhere('name', 'auditor_snapshot_compound_idx');

        expect($index)->toBeArray()
            ->and($index['columns'])->toBe(['status', 'category']);
    } finally {
        Schema::dropIfExists('auditor_index_columns');
    }
});

it('detects index column and foreign schema changes', function () {
    $table = [
        'name' => 'child',
        'columns' => [],
        'indexes' => [[
            'name' => 'child_lookup',
            'columns' => ['first'],
            'unique' => false,
        ]],
        'foreign_keys' => [[
            'name' => 'child_parent_fk',
            'columns' => ['parent_id'],
            'foreign_schema' => 'tenant_one',
            'foreign_table' => 'parents',
            'foreign_columns' => ['id'],
            'on_update' => 'no action',
            'on_delete' => 'cascade',
        ]],
    ];
    $before = ['driver' => 'pgsql', 'tables' => [$table]];
    $after = $before;
    $after['tables'][0]['indexes'][0]['columns'] = ['second'];
    $after['tables'][0]['foreign_keys'][0]['foreign_schema'] = 'tenant_two';

    $diff = (new SchemaComparator)->compare($before, $after);

    expect($diff['modified'])->toHaveCount(1)
        ->and($diff['modified'][0]['before']['indexes'][0]['columns'])->toBe(['first'])
        ->and($diff['modified'][0]['after']['indexes'][0]['columns'])->toBe(['second'])
        ->and($diff['modified'][0]['before']['foreign_keys'][0]['foreign_schema'])->toBe('tenant_one')
        ->and($diff['modified'][0]['after']['foreign_keys'][0]['foreign_schema'])->toBe('tenant_two');
});

it('preserves PostgreSQL schema identities when tables share a name', function () {
    $table = ['name' => 'orders', 'schema' => 'public', 'columns' => [], 'indexes' => [], 'foreign_keys' => []];
    $other = [...$table, 'schema' => 'tenant_one'];

    $before = ['driver' => 'pgsql', 'tables' => [$table, $other]];
    $after = $before;
    $after['tables'][1]['columns'][] = ['name' => 'state', 'type' => 'text', 'nullable' => true];

    $diff = (new SchemaComparator)->compare($before, $after);

    expect($diff['added'])->toBe([])
        ->and($diff['removed'])->toBe([])
        ->and($diff['modified'])->toHaveCount(1)
        ->and($diff['modified'][0]['schema'])->toBe('tenant_one');
});
