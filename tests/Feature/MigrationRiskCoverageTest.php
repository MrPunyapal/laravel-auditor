<?php

declare(strict_types=1);

use LaravelAuditor\Context\Migrations\MigrationRiskAnalyzer;
use LaravelAuditor\Context\Snapshots\SnapshotBuilder;

it('flags removed indexes and foreign keys without reporting a safe migration', function () {
    $before = app(SnapshotBuilder::class)->capture();
    $table = [
        'name' => 'invoices', 'schema' => null,
        'columns' => [['name' => 'id', 'type' => 'integer', 'nullable' => false]],
        'indexes' => [['name' => 'invoice_idx', 'columns' => ['id'], 'unique' => false]],
        'foreign_keys' => [[
            'name' => 'invoice_parent_fk', 'columns' => ['id'], 'foreign_schema' => null,
            'foreign_table' => 'parents', 'foreign_columns' => ['id'], 'on_update' => null, 'on_delete' => null,
        ]],
    ];
    $before['sections']['schema'] = ['driver' => 'pgsql', 'tables' => [$table]];
    $after = $before;
    $after['sections']['schema']['tables'][0]['indexes'] = [];
    $after['sections']['schema']['tables'][0]['foreign_keys'] = [];

    $result = app(MigrationRiskAnalyzer::class)->analyze($before, $after);
    expect(array_column($result['indicators'], 'code'))->toContain('index_removed', 'foreign_key_removed')
        ->and($result['deployment_safety_verified'])->toBeFalse();
});

it('does not treat a new table alone as a risk indicator', function () {
    $before = app(SnapshotBuilder::class)->capture();
    $before['sections']['schema'] = ['driver' => 'sqlite', 'tables' => []];
    $after = $before;
    $after['sections']['schema']['tables'][] = [
        'name' => 'new_table', 'schema' => null, 'columns' => [['name' => 'id', 'type' => 'integer', 'nullable' => false]],
        'indexes' => [], 'foreign_keys' => [],
    ];

    $result = app(MigrationRiskAnalyzer::class)->analyze($before, $after);

    expect($result['has_indicators'])->toBeFalse()
        ->and($result['deployment_safety_verified'])->toBeFalse();
});

it('reports a table rename as removal of the old table only', function () {
    $before = app(SnapshotBuilder::class)->capture();
    $before['sections']['schema'] = ['driver' => 'sqlite', 'tables' => [[
        'name' => 'legacy_orders', 'schema' => null,
        'columns' => [['name' => 'id', 'type' => 'integer', 'nullable' => false]],
        'indexes' => [], 'foreign_keys' => [],
    ]]];
    $after = $before;
    $after['sections']['schema']['tables'][0]['name'] = 'orders';

    $result = app(MigrationRiskAnalyzer::class)->analyze($before, $after);

    expect($result['indicators'])->toHaveCount(1)
        ->and($result['indicators'][0]['code'])->toBe('table_removed')
        ->and($result['indicators'][0]['table'])->toBe('legacy_orders');
});

it('distinguishes modified, renamed and composite indexes', function () {
    $before = app(SnapshotBuilder::class)->capture();
    $table = [
        'name' => 'orders', 'schema' => null,
        'columns' => [
            ['name' => 'account_id', 'type' => 'integer', 'nullable' => false],
            ['name' => 'team_id', 'type' => 'integer', 'nullable' => false],
            ['name' => 'slug', 'type' => 'varchar', 'nullable' => false],
            ['name' => 'label', 'type' => 'varchar', 'nullable' => false],
        ],
        'indexes' => [
            ['name' => 'orders_legacy_lookup', 'columns' => ['account_id', 'slug'], 'unique' => false],
            ['name' => 'orders_team_lookup', 'columns' => ['team_id', 'slug'], 'unique' => false],
        ],
        'foreign_keys' => [],
    ];
    $before['sections']['schema'] = ['driver' => 'pgsql', 'tables' => [$table]];
    $after = $before;
    $after['sections']['schema']['tables'][0]['indexes'] = [
        ['name' => 'orders_account_lookup', 'columns' => ['account_id', 'slug'], 'unique' => false],
        ['name' => 'orders_team_lookup', 'columns' => ['team_id', 'label'], 'unique' => false],
        ['name' => 'orders_team_slug_unique', 'columns' => ['team_id', 'slug'], 'unique' => true],
    ];

    $result = app(MigrationRiskAnalyzer::class)->analyze($before, $after);
    $messages = array_column($result['indicators'], 'message');

    expect(array_column($result['indicators'], 'code'))->toContain('index_removed', 'index_added', 'index_changed', 'unique_index_added')
        ->and($messages)->toContain(
            'Index removed: orders_legacy_lookup.',
            'Index added: orders_account_lookup.',
            'Index definition changed: orders_team_lookup.',
            'Unique index added: orders_team_slug_unique.',
        );
});

it('retains composite foreign-key evidence when a target definition changes', function () {
    $before = app(SnapshotBuilder::class)->capture();
    $table = [
        'name' => 'order_items', 'schema' => 'tenant_one',
        'columns' => [
            ['name' => 'tenant_id', 'type' => 'integer', 'nullable' => false],
            ['name' => 'order_id', 'type' => 'integer', 'nullable' => false],
        ],
        'indexes' => [],
        'foreign_keys' => [[
            'name' => 'order_items_order_fk', 'columns' => ['tenant_id', 'order_id'],
            'foreign_schema' => 'tenant_one', 'foreign_table' => 'orders',
            'foreign_columns' => ['tenant_id', 'id'], 'on_update' => 'cascade', 'on_delete' => 'restrict',
        ]],
    ];
    $before['sections']['schema'] = ['driver' => 'pgsql', 'tables' => [$table]];
    $after = $before;
    $after['sections']['schema']['tables'][0]['foreign_keys'][0]['on_delete'] = 'cascade';

    $result = app(MigrationRiskAnalyzer::class)->analyze($before, $after);
    $constraints = array_column($result['indicators'], 'constraint');

    expect(array_column($result['indicators'], 'code'))->toContain('foreign_key_removed', 'foreign_key_added')
        ->and($constraints)->toContain($table['foreign_keys'][0])
        ->and($constraints)->toContain($after['sections']['schema']['tables'][0]['foreign_keys'][0]);
});
