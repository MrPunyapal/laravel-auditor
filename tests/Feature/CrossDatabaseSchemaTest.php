<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use LaravelAuditor\Context\Snapshots\SnapshotBuilder;
use LaravelAuditor\Context\Snapshots\SnapshotComparator;

it('reads and compares actual MySQL and PostgreSQL indexes, columns and foreign keys', function () {
    $driver = getenv('AUDITOR_SCHEMA_DRIVER');

    if (! in_array($driver, ['mysql', 'pgsql'], true)) {
        $this->markTestSkipped('Requires AUDITOR_SCHEMA_DRIVER=mysql or pgsql and a disposable database.');
    }

    $connection = 'auditor_schema_integration';
    $previous = config('database.default');

    config([
        'database.default' => $connection,
        "database.connections.{$connection}" => [
            'driver' => $driver,
            'host' => '127.0.0.1',
            'port' => $driver === 'mysql' ? 3306 : 5432,
            'database' => 'auditor_ci',
            'username' => $driver === 'mysql' ? 'root' : 'auditor',
            'password' => $driver === 'mysql' ? 'root' : 'auditor',
            'charset' => $driver === 'mysql' ? 'utf8mb4' : 'utf8',
            'collation' => $driver === 'mysql' ? 'utf8mb4_unicode_ci' : null,
            'prefix' => '',
            'prefix_indexes' => true,
            'schema' => 'public',
            'sslmode' => 'prefer',
        ],
    ]);

    DB::purge($connection);
    $schema = DB::connection($connection)->getSchemaBuilder();

    try {
        $schema->dropIfExists('auditor_schema_children');
        $schema->dropIfExists('auditor_schema_parents');

        $schema->create('auditor_schema_parents', static function (Blueprint $table): void {
            $table->id();
        });

        $schema->create('auditor_schema_children', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('parent_id');
            $table->string('state')->default('do-not-snapshot-secret');
            $table->foreign('parent_id', 'auditor_schema_parent_fk')->references('id')->on('auditor_schema_parents');
            $table->index(['state', 'parent_id'], 'auditor_schema_multi_idx');
        });

        $before = app(SnapshotBuilder::class)->capture(withSchema: true);
        $tables = $before['sections']['schema']['tables'];
        $child = collect($tables)->firstWhere('name', 'auditor_schema_children');

        expect($child)->toBeArray()
            ->and($before['sections']['schema']['driver'])->toBe($driver)
            ->and($child)->toHaveKeys(['columns', 'indexes', 'foreign_keys'])
            ->and(collect($child['indexes'])->firstWhere('name', 'auditor_schema_multi_idx')['columns'])
            ->toBe(['state', 'parent_id'])
            ->and(collect($child['foreign_keys'])->firstWhere('name', 'auditor_schema_parent_fk')['foreign_table'])
            ->toBe('auditor_schema_parents')
            ->and(json_encode($before, JSON_THROW_ON_ERROR))->not->toContain('do-not-snapshot-secret');

        if ($driver === 'pgsql') {
            expect($child['schema'])->toBe('public');
        } else {
            expect($child['schema'])->toBeNull();
        }

        $schema->table('auditor_schema_children', static function (Blueprint $table): void {
            $table->string('extra')->nullable();
        });

        $after = app(SnapshotBuilder::class)->capture(withSchema: true);
        $diff = app(SnapshotComparator::class)->compare($before, $after);

        expect($diff['complete'])->toBeTrue()
            ->and($diff['has_changes'])->toBeTrue()
            ->and($diff['summary']['tables_modified'])->toBeGreaterThanOrEqual(1)
            ->and(collect($diff['changes']['schema']['modified'])->firstWhere('table', 'auditor_schema_children'))->toBeArray();
    } finally {
        $schema->dropIfExists('auditor_schema_children');
        $schema->dropIfExists('auditor_schema_parents');
        DB::disconnect($connection);
        config(['database.default' => $previous]);
    }
});


it('keeps same-named PostgreSQL tables distinct across configured schemas', function () {
    if (getenv('AUDITOR_SCHEMA_DRIVER') !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL integration runner only.');
    }

    $connection = 'auditor_multi_schema_integration';
    $previous = config('database.default');

    config([
        'database.default' => $connection,
        "database.connections.{$connection}" => [
            'driver' => 'pgsql',
            'host' => '127.0.0.1',
            'port' => 5432,
            'database' => 'auditor_ci',
            'username' => 'auditor',
            'password' => 'auditor',
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'public,auditor_snapshot_tenant',
        ],
    ]);

    DB::purge($connection);
    $db = DB::connection($connection);

    try {
        $db->statement('CREATE SCHEMA auditor_snapshot_tenant');
        $db->statement('CREATE TABLE public.auditor_snapshot_duplicate (id BIGINT)');
        $db->statement('CREATE TABLE auditor_snapshot_tenant.auditor_snapshot_duplicate (id BIGINT, extra TEXT)');

        $snapshot = app(SnapshotBuilder::class)->capture(withSchema: true);
        $matches = array_values(array_filter(
            $snapshot['sections']['schema']['tables'],
            static fn (array $table): bool => $table['name'] === 'auditor_snapshot_duplicate',
        ));

        expect($matches)->toHaveCount(2)
            ->and(array_column($matches, 'schema'))->toBe(['auditor_snapshot_tenant', 'public'])
            ->and($matches[0]['columns'])->toHaveCount(2)
            ->and($matches[1]['columns'])->toHaveCount(1);

        $diff = app(SnapshotComparator::class)->compare($snapshot, $snapshot);

        expect($diff['has_changes'])->toBeFalse()
            ->and($diff['summary']['tables_modified'])->toBe(0);
    } finally {
        $db->statement('DROP TABLE IF EXISTS auditor_snapshot_tenant.auditor_snapshot_duplicate');
        $db->statement('DROP TABLE IF EXISTS public.auditor_snapshot_duplicate');
        $db->statement('DROP SCHEMA IF EXISTS auditor_snapshot_tenant');
        DB::purge($connection);
        config(['database.default' => $previous]);
    }
});
