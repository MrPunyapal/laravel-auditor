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
        ->and($result['summary'])->toBe(['routes_added' => 0, 'routes_removed' => 0, 'routes_modified' => 1, 'route_order_changed' => false])
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
    $after['sections']['route_order'][] = [
        'domain' => null,
        'uri' => '__new_auditor_route__',
        'methods' => ['POST'],
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

it('rejects incomplete and malformed snapshot metadata instead of reporting no changes', function () {
    $snapshot = app(SnapshotBuilder::class)->capture();
    $comparator = app(SnapshotComparator::class);

    $invalid = $snapshot;
    unset($invalid['sections']['routes'][0]['domain']);
    expect(fn () => $comparator->compare($snapshot, $invalid))->toThrow(RuntimeException::class);

    $invalid = $snapshot;
    $invalid['sections']['routes'][0]['methods'] = ['GET', 123];
    expect(fn () => $comparator->compare($snapshot, $invalid))->toThrow(RuntimeException::class);

    $invalid = $snapshot;
    $invalid['sections']['authorization']['gates'] = ['valid', ['invalid']];
    expect(fn () => $comparator->compare($snapshot, $invalid))->toThrow(RuntimeException::class);

    $invalid = $snapshot;
    $invalid['sections']['authorization']['policies'] = ['App\\Models\\Post' => ['invalid']];
    expect(fn () => $comparator->compare($snapshot, $invalid))->toThrow(RuntimeException::class);
});

it('reports route registration reordering even when route definitions are unchanged', function () {
    $router = app('router');
    $router->get('/auditor-overlap/{slug}', static fn (): string => 'dynamic');
    $router->get('/auditor-overlap/static', static fn (): string => 'static');

    $before = app(SnapshotBuilder::class)->capture();
    $after = $before;

    $first = array_search('auditor-overlap/{slug}', array_column($before['sections']['route_order'], 'uri'), true);
    $second = array_search('auditor-overlap/static', array_column($before['sections']['route_order'], 'uri'), true);

    expect($first)->not->toBeFalse()->and($second)->not->toBeFalse();

    [$after['sections']['route_order'][$first], $after['sections']['route_order'][$second]] = [
        $after['sections']['route_order'][$second],
        $after['sections']['route_order'][$first],
    ];

    $result = app(SnapshotComparator::class)->compare($before, $after);

    expect($result['has_changes'])->toBeTrue()
        ->and($result['summary']['route_order_changed'])->toBeTrue()
        ->and($result['summary']['routes_modified'])->toBe(0)
        ->and($result['changes']['route_order']['changed'])->toBeTrue();
});

it('ignores route additions when comparing relative order of existing routes', function () {
    $before = app(SnapshotBuilder::class)->capture();
    $after = $before;

    $new = ['domain' => null, 'uri' => 'auditor-order-added', 'methods' => ['GET', 'HEAD']];
    $after['sections']['route_order'][] = $new;
    $after['sections']['routes'][] = [...$new, 'name' => null, 'action' => 'closure', 'middleware' => []];

    $result = app(SnapshotComparator::class)->compare($before, $after);

    expect($result['summary']['routes_added'])->toBe(1)
        ->and($result['summary']['route_order_changed'])->toBeFalse();
});

it('rejects missing or mismatched registration-order information', function () {
    $before = app(SnapshotBuilder::class)->capture();
    $after = $before;
    unset($after['sections']['route_order']);

    expect(fn () => app(SnapshotComparator::class)->compare($before, $after))
        ->toThrow(RuntimeException::class);

    $after = $before;
    array_pop($after['sections']['route_order']);

    expect(fn () => app(SnapshotComparator::class)->compare($before, $after))
        ->toThrow(RuntimeException::class);
});
