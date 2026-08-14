<?php

declare(strict_types=1);

use LaravelAuditor\Audit\Evidence\Evidence;
use LaravelAuditor\Audit\Evidence\EvidenceCollection;

it('creates evidence via factory methods', function () {
    $file = Evidence::file('app/Http/Controllers/PostController.php', 42, 44, 'destroy method');

    expect($file->type)->toBe('file');
    expect($file->reference)->toBe('app/Http/Controllers/PostController.php');
    expect($file->line)->toBe(42);
    expect($file->endLine)->toBe(44);
    expect($file->detail)->toBe('destroy method');

    $symbol = Evidence::symbol('App\Models\Post::scopeVisible');

    expect($symbol->type)->toBe('symbol');
    expect($symbol->reference)->toBe('App\Models\Post::scopeVisible');

    $route = Evidence::route('GET', '/posts');

    expect($route->type)->toBe('route');
    expect($route->reference)->toBe('GET /posts');

    $config = Evidence::config('app.debug');

    expect($config->type)->toBe('config');
    expect($config->reference)->toBe('app.debug');

    $dependency = Evidence::dependency('laravel/framework', 'v13.0.0');

    expect($dependency->type)->toBe('dependency');
    expect($dependency->reference)->toBe('laravel/framework');
    expect($dependency->detail)->toBe('v13.0.0');

    $migration = Evidence::migration('database/migrations/2026_01_01_000000_create_posts_table.php');

    expect($migration->type)->toBe('migration');

    expect(Evidence::query('select * from posts')->type)->toBe('query');
    expect(Evidence::test('tests/Feature/PostTest.php')->type)->toBe('test');
    expect(Evidence::log('storage/logs/laravel.log')->type)->toBe('log');
});

it('serializes evidence to and from an array', function () {
    $evidence = Evidence::file('app/Http/Controllers/PostController.php', 42, null, 'destroy');

    $data = $evidence->toArray();
    $roundTripped = Evidence::fromArray($data);

    expect($roundTripped->type)->toBe($evidence->type);
    expect($roundTripped->reference)->toBe($evidence->reference);
    expect($roundTripped->line)->toBe(42);
    expect($roundTripped->endLine)->toBeNull();
    expect($roundTripped->detail)->toBe('destroy');
});

it('collects evidence into a collection', function () {
    $collection = new EvidenceCollection(
        Evidence::file('a.php', 1),
        Evidence::symbol('App\Models\Post'),
    );

    expect($collection)->toHaveCount(2);
    expect($collection->all())->toHaveCount(2);
    expect($collection->isEmpty())->toBeFalse();

    $collection->add(Evidence::route('GET', '/'));
    expect($collection)->toHaveCount(3);
});

it('creates an evidence collection from an iterable', function () {
    $collection = EvidenceCollection::fromIterable([
        Evidence::file('a.php'),
        Evidence::file('b.php'),
    ]);

    expect($collection)->toHaveCount(2);
});

it('serializes an evidence collection to arrays', function () {
    $collection = new EvidenceCollection(Evidence::file('a.php', 1));

    $arrays = $collection->toArray();

    expect($arrays)->toBeArray()->toHaveCount(1);
    expect($arrays[0]['reference'])->toBe('a.php');
    expect($collection->jsonSerialize()[0]['reference'])->toBe('a.php');

    $collection[] = Evidence::file('b.php');
    $collection[1] = Evidence::file('c.php');

    expect($collection)->toHaveCount(2);
    expect(isset($collection[0]))->toBeTrue();
    expect($collection[1]->reference)->toBe('c.php');

    unset($collection[1]);

    expect($collection)->toHaveCount(1);
});
