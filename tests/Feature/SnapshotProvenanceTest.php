<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LaravelAuditor\Context\Snapshots\SnapshotBuilder;
use LaravelAuditor\Context\Snapshots\SnapshotComparator;

it('keeps default snapshots compatible and optionally records non-secret capture metadata', function () {
    expect(Artisan::call('auditor:snapshot'))->toBe(0);
    $plain = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    expect($plain)->not->toHaveKey('provenance');

    expect(Artisan::call('auditor:snapshot', ['--with-provenance' => true]))->toBe(0);
    $recorded = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    expect($recorded['provenance'])->toHaveKeys(['revision', 'dirty', 'routes_cached', 'php_version', 'laravel_version'])
        ->and($recorded['provenance']['php_version'])->toBe(PHP_VERSION)
        ->and($recorded['provenance'])->not->toHaveKeys(['path', 'environment', 'database']);
});

it('reports unknown legacy provenance and rejects mismatched or malformed capture metadata', function () {
    $legacy = app(SnapshotBuilder::class)->capture();
    $before = $legacy;
    $before['provenance'] = [
        'revision' => str_repeat('a', 40),
        'dirty' => false,
        'routes_cached' => false,
        'php_version' => '8.4.0',
        'laravel_version' => '13.0.0',
    ];
    $after = $before;
    $after['provenance']['revision'] = str_repeat('b', 40);
    $comparator = app(SnapshotComparator::class);

    expect($comparator->compare($legacy, $before)['provenance']['route_cache_matches'])->toBeNull()
        ->and($comparator->compare($before, $after)['provenance']['route_cache_matches'])->toBeTrue()
        ->and($comparator->compare($before, $after)['provenance']['artifact_authenticity_verified'])->toBeFalse();

    $after['provenance']['routes_cached'] = true;
    expect(fn () => $comparator->compare($before, $after))->toThrow(RuntimeException::class, 'route-cache states differ');

    $after['provenance']['revision'] = 'not-a-commit';
    expect(fn () => $comparator->compare($before, $after))->toThrow(RuntimeException::class, 'Invalid snapshot provenance');
});
