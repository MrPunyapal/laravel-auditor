<?php

declare(strict_types=1);

use LaravelAuditor\Context\Migrations\MigrationRiskAnalyzer;
use LaravelAuditor\Context\Snapshots\SnapshotBuilder;

function migrationRiskSnapshot(array $tables, string $driver = 'sqlite'): array
{
    $snapshot = app(SnapshotBuilder::class)->capture();
    $snapshot['sections']['schema'] = ['driver' => $driver, 'tables' => $tables];

    return $snapshot;
}

function migrationRiskTable(string $name = 'users', ?string $schema = null): array
{
    return [
        'name' => $name,
        'schema' => $schema,
        'columns' => [['name' => 'id', 'type' => 'integer', 'nullable' => false]],
        'indexes' => [],
        'foreign_keys' => [],
    ];
}

it('flags removed tables and columns without claiming data was lost', function () {
    $old = migrationRiskTable();
    $old['columns'][] = ['name' => 'email', 'type' => 'varchar', 'nullable' => true];

    $after = migrationRiskTable();
    $result = app(MigrationRiskAnalyzer::class)->analyze(migrationRiskSnapshot([$old]), migrationRiskSnapshot([$after]));

    expect(array_column($result['indicators'], 'code'))->toContain('column_removed')
        ->and($result['deployment_safety_verified'])->toBeFalse()
        ->and($result['coverage'])->toBe('snapshot_structure_only');

    $removed = app(MigrationRiskAnalyzer::class)->analyze(migrationRiskSnapshot([$old]), migrationRiskSnapshot([]));
    expect(array_column($removed['indicators'], 'code'))->toContain('table_removed');
});

it('flags non-null additions, type conversions and tightened nullability', function () {
    $old = migrationRiskTable();
    $old['columns'][] = ['name' => 'status', 'type' => 'varchar(10)', 'nullable' => true];

    $after = $old;
    $after['columns'][1] = ['name' => 'status', 'type' => 'varchar(5)', 'nullable' => false];
    $after['columns'][] = ['name' => 'required', 'type' => 'integer', 'nullable' => false];

    $result = app(MigrationRiskAnalyzer::class)->analyze(migrationRiskSnapshot([$old]), migrationRiskSnapshot([$after]));
    expect(array_column($result['indicators'], 'code'))->toBe([
        'required_column_added', 'column_type_changed', 'nullability_tightened',
    ]);
});

it('checks new indexes and foreign keys without inferring exact lock behavior', function () {
    $old = migrationRiskTable();
    $after = $old;
    $after['indexes'][] = ['name' => 'users_email_unique', 'columns' => ['id'], 'unique' => true];
    $after['foreign_keys'][] = [
        'name' => 'users_parent_fk', 'columns' => ['id'], 'foreign_schema' => null,
        'foreign_table' => 'parents', 'foreign_columns' => ['id'], 'on_update' => null, 'on_delete' => 'cascade',
    ];

    $result = app(MigrationRiskAnalyzer::class)->analyze(migrationRiskSnapshot([$old]), migrationRiskSnapshot([$after]));
    expect(array_column($result['indicators'], 'code'))->toContain('unique_index_added', 'foreign_key_added')
        ->and($result['summary']['review'])->toBe(2)
        ->and(collect($result['indicators'])->firstWhere('code', 'foreign_key_added')['constraint'])->toBe($after['foreign_keys'][0]);
});

it('includes distinct identities for multiple removed foreign keys', function () {
    $before = migrationRiskTable();
    $before['foreign_keys'] = [
        [
            'name' => 'users_parent_fk', 'columns' => ['parent_id'], 'foreign_schema' => null,
            'foreign_table' => 'parents', 'foreign_columns' => ['id'], 'on_update' => null, 'on_delete' => 'cascade',
        ],
        [
            'name' => 'users_team_fk', 'columns' => ['team_id'], 'foreign_schema' => 'tenant',
            'foreign_table' => 'teams', 'foreign_columns' => ['id'], 'on_update' => 'cascade', 'on_delete' => null,
        ],
    ];
    $after = $before;
    $after['foreign_keys'] = [];
    $beforeSnapshot = migrationRiskSnapshot([$before], 'pgsql');
    $afterSnapshot = migrationRiskSnapshot([$after], 'pgsql');
    $analyzer = app(MigrationRiskAnalyzer::class);

    $result = $analyzer->analyze($beforeSnapshot, $afterSnapshot);
    $removed = array_values(array_filter(
        $result['indicators'],
        static fn (array $indicator): bool => $indicator['code'] === 'foreign_key_removed',
    ));

    expect($removed)->toHaveCount(2)
        ->and(array_column(array_column($removed, 'constraint'), 'name'))->toBe(['users_parent_fk', 'users_team_fk'])
        ->and($result)->toBe($analyzer->analyze($beforeSnapshot, $afterSnapshot));
});

it('distinguishes PostgreSQL schema identities and produces stable results', function () {
    $first = migrationRiskTable('events', 'public');
    $second = migrationRiskTable('events', 'tenant_one');
    $after = $second;
    $after['columns'][] = ['name' => 'state', 'type' => 'text', 'nullable' => false];

    $beforeSnapshot = migrationRiskSnapshot([$first, $second], 'pgsql');
    $afterSnapshot = migrationRiskSnapshot([$first, $after], 'pgsql');
    $analyzer = app(MigrationRiskAnalyzer::class);

    $result = $analyzer->analyze($beforeSnapshot, $afterSnapshot);

    expect($result)->toBe($analyzer->analyze($beforeSnapshot, $afterSnapshot))
        ->and($result['indicators'])->toHaveCount(1)
        ->and($result['indicators'][0]['schema'])->toBe('tenant_one');
});

it('reports an empty structural indicator list without declaring a deployment safe', function () {
    $before = migrationRiskSnapshot([migrationRiskTable()]);
    $result = app(MigrationRiskAnalyzer::class)->analyze($before, $before);

    expect($result['has_indicators'])->toBeFalse()
        ->and($result['deployment_safety_verified'])->toBeFalse()
        ->and($result['limitations'])->toContain('No indicators does not mean a migration or deployment is safe.');
});

it('refuses missing schemas, different drivers and malformed metadata', function () {
    $before = migrationRiskSnapshot([migrationRiskTable()]);
    $withoutSchema = app(SnapshotBuilder::class)->capture();

    expect(fn () => app(MigrationRiskAnalyzer::class)->analyze($before, $withoutSchema))
        ->toThrow(RuntimeException::class);
    expect(fn () => app(MigrationRiskAnalyzer::class)->analyze($before, migrationRiskSnapshot([], 'pgsql')))
        ->toThrow(RuntimeException::class);

    $invalid = migrationRiskSnapshot([migrationRiskTable()]);
    $invalid['sections']['schema']['tables'][0]['columns'][0]['name'] = null;
    expect(fn () => app(MigrationRiskAnalyzer::class)->analyze($before, $invalid))
        ->toThrow(RuntimeException::class);
});

it('rejects duplicate canonical foreign-key entries instead of reporting identical snapshots clean', function () {
    $table = migrationRiskTable();
    $foreignKey = [
        'name' => 'users_parent_fk', 'columns' => ['parent_id'], 'foreign_schema' => null,
        'foreign_table' => 'parents', 'foreign_columns' => ['id'], 'on_update' => null, 'on_delete' => null,
    ];
    $table['foreign_keys'] = [$foreignKey, array_reverse($foreignKey)];
    $snapshot = migrationRiskSnapshot([$table]);

    expect(fn () => app(MigrationRiskAnalyzer::class)->analyze($snapshot, $snapshot))
        ->toThrow(RuntimeException::class, 'Duplicate foreign key in schema snapshot.');
});
