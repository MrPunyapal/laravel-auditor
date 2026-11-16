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

it('does not claim dropped columns inside a newly created table', function () {
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
