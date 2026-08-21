<?php

declare(strict_types=1);

use LaravelAuditor\Context\Collectors\DatabaseSchemaCollector;
use LaravelAuditor\Context\Collectors\DependenciesCollector;
use LaravelAuditor\Context\Collectors\ModelsCollector;
use LaravelAuditor\Context\Collectors\RoutesCollector;
use LaravelAuditor\Context\FilterableCollector;

it('declares read-only filters on the high-volume collectors', function () {
    expect(app(RoutesCollector::class))->toBeInstanceOf(FilterableCollector::class);
    expect(app(ModelsCollector::class))->toBeInstanceOf(FilterableCollector::class);
    expect(app(DatabaseSchemaCollector::class))->toBeInstanceOf(FilterableCollector::class);
    expect(app(DependenciesCollector::class))->toBeInstanceOf(FilterableCollector::class);

    expect(app(RoutesCollector::class)->filters())->toHaveKeys(['uri', 'name', 'action', 'method']);
    expect(app(ModelsCollector::class)->filters())->toHaveKeys(['class', 'table']);
    expect(app(DatabaseSchemaCollector::class)->filters())->toHaveKeys(['table']);
    expect(app(DependenciesCollector::class)->filters())->toHaveKeys(['package']);
});

it('narrows routes by uri substring while reporting the total inventory size', function () {
    $collector = app(RoutesCollector::class);

    $filtered = $collector->collectFiltered(['uri' => 'posts']);

    expect($filtered['filtered'])->toBeTrue();
    expect($filtered['total_count'])->toBe($collector->collect()['count']);
    expect($filtered['count'])->toBeGreaterThan(0);
    expect($filtered['routes'])->not->toBeEmpty();
    expect(collect($filtered['routes'])->pluck('uri')->all())->each->toContain('posts');
});

it('matches routes by exact verb', function () {
    $data = app(RoutesCollector::class)->collectFiltered(['method' => 'get']);

    expect($data['count'])->toBeGreaterThanOrEqual(1);

    foreach ($data['routes'] as $route) {
        expect(array_map('strtoupper', $route['methods']))->toContain('GET');
    }
});

it('returns an empty route set with the total preserved when nothing matches', function () {
    $data = app(RoutesCollector::class)->collectFiltered(['uri' => 'no-such-route-prefix-xyz']);

    expect($data['count'])->toBe(0);
    expect($data['routes'])->toBe([]);
    expect($data['filtered'])->toBeTrue();
    expect($data['total_count'])->toBeGreaterThanOrEqual(1);
});

it('combines route filters with AND semantics', function () {
    $data = app(RoutesCollector::class)->collectFiltered(['uri' => 'posts', 'name' => 'posts.show']);

    expect($data['count'])->toBe(1);
    expect($data['routes'][0]['name'])->toBe('posts.show');
});

it('leaves unfiltered route payloads untouched', function () {
    $data = app(RoutesCollector::class)->collect();

    expect($data)->not->toHaveKey('filtered');
    expect($data)->not->toHaveKey('total_count');
});

it('keeps every documented field when a filter is applied', function () {
    $unfiltered = app(RoutesCollector::class)->collect();
    $filtered = app(RoutesCollector::class)->collectFiltered(['uri' => 'posts']);

    expect(array_keys($filtered))->toContain(...array_keys($unfiltered));
    expect($filtered['routes'][0] ?? [])->toHaveKeys(array_keys($unfiltered['routes'][0]));
});

it('narrows models by class and table substrings', function () {
    $collector = app(ModelsCollector::class);

    $byClass = $collector->collectFiltered(['class' => 'Post']);
    $byTable = $collector->collectFiltered(['table' => 'posts']);

    expect($byClass['filtered'])->toBeTrue();
    expect($byClass['total_count'])->toBe($collector->collect()['count']);
    expect(collect($byClass['models'])->pluck('class')->all())->each->toContain('Post');

    expect($byTable['count'])->toBeGreaterThanOrEqual(1);
    expect(collect($byTable['models'])->pluck('table')->all())->each->toContain('posts');
});

it('keeps dependency filtering away from advisory data', function () {
    $collector = app(DependenciesCollector::class);
    $full = $collector->collect();

    $data = $collector->collectFiltered(['package' => 'laravel']);

    expect($data['filtered'])->toBeTrue();
    expect($data['total_count'])->toBe($full['count']);
    expect($data['count'])->toBe(count($data['packages']));
    expect(array_keys($data['packages']))->toContain('laravel/framework');
    expect($data['requires'])->toEqual($full['requires']);
    expect($data['requires_dev'])->toEqual($full['requires_dev']);
    expect($data['composer_audit'])->toEqual($full['composer_audit']);
});

it('narrows schema tables when the database is available', function () {
    $collector = app(DatabaseSchemaCollector::class);
    $full = $collector->collect();

    if (($full['available'] ?? false) !== true || ($full['tables'] ?? []) === []) {
        $this->markTestSkipped('No database tables are available in this environment.');
    }

    $needle = (string) $full['tables'][0]['name'];

    $data = $collector->collectFiltered(['table' => $needle]);

    expect($data['available'])->toBeTrue();
    expect($data['filtered'])->toBeTrue();
    expect($data['total_count'])->toBe(count($full['tables']));
    expect(collect($data['tables'])->pluck('name')->all())->each->toContain($needle);
});

it('passes unavailable schema payloads through unmodified', function () {
    $collector = app(DatabaseSchemaCollector::class);
    $full = $collector->collect();

    if (($full['available'] ?? false) === true) {
        $this->markTestSkipped('A database is available in this environment.');
    }

    expect($collector->collectFiltered(['table' => 'users']))->toEqual($full);
});
