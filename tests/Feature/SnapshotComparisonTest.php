<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LaravelAuditor\Context\Snapshots\SnapshotBuilder;
use LaravelAuditor\Context\Snapshots\SnapshotComparator;

it('compares routes by identity and preserves middleware changes', function () {
    $before = app(SnapshotBuilder::class)->capture();
    $after = $before;
    $after['sections']['routes'][0]['middleware'][] = 'can:update,post';

    $result = app(SnapshotComparator::class)->compare($before, $after);

    expect($result['complete'])->toBeTrue()
        ->and($result['has_changes'])->toBeTrue()
        ->and($result['summary'])->toBe(['routes_added' => 0, 'routes_removed' => 0, 'routes_modified' => 1])
        ->and($result['changes']['routes']['modified'][0]['before']['middleware'])->not->toContain('can:update,post')
        ->and($result['changes']['routes']['modified'][0]['after']['middleware'])->toContain('can:update,post');
});

it('recognizes added and removed routes', function () {
    $before = app(SnapshotBuilder::class)->capture();
    $after = $before;
    $after['sections']['routes'][] = [
        'methods' => ['POST'],
        'uri' => '__new_auditor_route__',
        'domain' => null,
        'name' => null,
        'action' => 'closure',
        'middleware' => [],
    ];

    $comparator = app(SnapshotComparator::class);

    expect($comparator->compare($before, $after)['summary']['routes_added'])->toBe(1)
        ->and($comparator->compare($after, $before)['summary']['routes_removed'])->toBe(1);
});

it('does not hide missing data or accept incompatible snapshot versions', function () {
    $snapshot = app(SnapshotBuilder::class)->capture();
    $comparator = app(SnapshotComparator::class);

    expect(fn () => $comparator->compare($snapshot, ['version' => 1]))
        ->toThrow(RuntimeException::class);

    $invalid = $snapshot;
    $invalid['sections']['authorization'] = [];
    expect(fn () => $comparator->compare($snapshot, $invalid))
        ->toThrow(RuntimeException::class);

    $invalid = $snapshot;
    $invalid['version'] = 99;
    expect(fn () => $comparator->compare($snapshot, $invalid))
        ->toThrow(RuntimeException::class);
});

it('exposes a structured comparison via Artisan', function () {
    $before = sys_get_temp_dir().'/laravel-auditor-before-'.uniqid().'.json';
    $after = sys_get_temp_dir().'/laravel-auditor-after-'.uniqid().'.json';

    try {
        $snapshot = app(SnapshotBuilder::class)->capture();

        file_put_contents($before, json_encode($snapshot, JSON_THROW_ON_ERROR));
        file_put_contents($after, json_encode($snapshot, JSON_THROW_ON_ERROR));

        expect(Artisan::call('auditor:compare', ['before' => $before, 'after' => $after]))->toBe(0);

        $diff = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        expect($diff['complete'])->toBeTrue()->and($diff['has_changes'])->toBeFalse();
    } finally {
        foreach ([$before, $after] as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }
});
