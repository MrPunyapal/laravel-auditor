<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\MySqlBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LaravelAuditor\Context\Collectors\DatabaseSchemaCollector;
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

it('refuses SQLite generated columns rather than silently omitting their definitions', function () {
    DB::statement('CREATE TABLE auditor_generated_columns (value INTEGER, doubled INTEGER GENERATED ALWAYS AS (value * 2) STORED)');

    try {
        expect(fn () => app(SnapshotBuilder::class)->capture(withSchema: true))
            ->toThrow(RuntimeException::class, 'cannot be captured completely');
    } finally {
        Schema::dropIfExists('auditor_generated_columns');
    }
});

it('refuses SQLite partial indexes rather than silently omitting their predicates', function () {
    Schema::create('auditor_partial_indexes', function (Blueprint $table): void {
        $table->integer('value');
    });
    DB::statement('CREATE INDEX auditor_partial_value ON auditor_partial_indexes (value) WHERE value > 0');

    try {
        expect(fn () => app(SnapshotBuilder::class)->capture(withSchema: true))
            ->toThrow(RuntimeException::class, 'cannot be captured completely');
    } finally {
        Schema::dropIfExists('auditor_partial_indexes');
    }
});

it('rejects malformed nested schema metadata instead of reporting identical snapshots as clean', function (string $field, array $records) {
    $table = ['name' => 'users', 'columns' => [], 'indexes' => [], 'foreign_keys' => []];
    $table[$field] = $records;
    $snapshot = ['driver' => 'sqlite', 'tables' => [$table]];

    expect(fn () => (new SchemaComparator)->compare($snapshot, $snapshot))
        ->toThrow(RuntimeException::class);
})->with([
    'non-list columns' => ['columns', ['id' => ['name' => 'id', 'type' => 'integer', 'nullable' => false]]],
    'missing column metadata' => ['columns', [[]]],
    'invalid nullable flag' => ['columns', [['name' => 'id', 'type' => 'integer', 'nullable' => 'false']]],
    'duplicate columns' => ['columns', [
        ['name' => 'id', 'type' => 'integer', 'nullable' => false],
        ['name' => 'id', 'type' => 'integer', 'nullable' => false],
    ]],
    'missing index metadata' => ['indexes', [[]]],
    'invalid index columns' => ['indexes', [['name' => 'idx', 'columns' => [null], 'unique' => false]]],
    'missing foreign-key metadata' => ['foreign_keys', [[]]],
    'incomplete foreign-key column mapping' => ['foreign_keys', [[
        'name' => null, 'columns' => ['parent_id'], 'foreign_schema' => null,
        'foreign_table' => 'parents', 'foreign_columns' => [], 'on_update' => null, 'on_delete' => null,
    ]]],
]);

it('omits MySQL database names and rejects foreign keys to another database', function (string $foreignDatabase) {
    $schema = Mockery::mock(MySqlBuilder::class);
    $schema->shouldReceive('getCurrentSchemaListing')->andReturn(['private_database']);
    $schema->shouldReceive('getTables')->with(['private_database'])->andReturn([
        ['name' => 'children', 'schema_qualified_name' => 'private_database.children'],
    ]);
    $schema->shouldReceive('getColumns')->with('private_database.children')->andReturn([]);
    $schema->shouldReceive('getIndexes')->with('private_database.children')->andReturn([]);
    $schema->shouldReceive('getForeignKeys')->with('private_database.children')->andReturn([[
        'name' => 'parent_fk', 'columns' => ['parent_id'], 'foreign_schema' => $foreignDatabase,
        'foreign_table' => 'parents', 'foreign_columns' => ['id'], 'on_update' => 'no action', 'on_delete' => 'cascade',
    ]]);
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getName')->andReturn('private_connection');
    $connection->shouldReceive('getDatabaseName')->andReturn('private_database');
    $connection->shouldReceive('getSchemaBuilder')->andReturn($schema);
    $db = Mockery::mock(DatabaseManager::class);
    $db->shouldReceive('connection')->andReturn($connection);
    app()->instance(DatabaseSchemaCollector::class, new DatabaseSchemaCollector($db, app('config')));
    $builder = app(SnapshotBuilder::class);

    if ($foreignDatabase !== 'private_database') {
        expect(fn () => $builder->capture(withSchema: true))->toThrow(RuntimeException::class);

        return;
    }

    $snapshot = $builder->capture(withSchema: true);
    expect(json_encode($snapshot, JSON_THROW_ON_ERROR))->not->toContain('private_database', 'private_connection')
        ->and($snapshot['sections']['schema']['tables'][0]['foreign_keys'][0]['foreign_schema'])->toBeNull();
})->with(['private_database', 'another_private_database']);
