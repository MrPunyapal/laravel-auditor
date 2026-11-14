<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LaravelAuditor\Context\Snapshots\SnapshotBuilder;

it('captures a versioned, deterministic snapshot without timestamps or database credentials', function () {
    $first = app(SnapshotBuilder::class)->capture();
    $second = app(SnapshotBuilder::class)->capture();

    expect($first)->toEqual($second)
        ->and($first['format'])->toBe(SnapshotBuilder::FORMAT)
        ->and($first['version'])->toBe(1)
        ->and($first['sections'])->toHaveKeys(['routes', 'route_order', 'authorization'])
        ->and($first['sections']['routes'])->not->toBeEmpty()
        ->and($first['sections']['authorization'])->toHaveKeys(['gates', 'policies', 'policy_files', 'middleware_aliases'])
        ->and($first)->not->toHaveKeys(['generated_at', 'database']);
});

it('exposes snapshot JSON via Artisan and writes it to a file', function () {
    $path = sys_get_temp_dir().'/laravel-auditor-snapshot-'.uniqid().'.json';

    try {
        expect(Artisan::call('auditor:snapshot'))->toBe(0);
        $stdout = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->artisan('auditor:snapshot', ['--output' => $path])
            ->expectsOutputToContain('Snapshot written')
            ->assertSuccessful();

        $saved = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        expect($saved)->toEqual($stdout);
    } finally {
        if (file_exists($path)) {
            unlink($path);
        }
    }
});

it('keeps the real route registration order separate from sorted route metadata', function () {
    $router = app('router');
    $router->get('/auditor-order-first/{slug}', static fn (): string => 'first')
        ->name('auditor.order.first');
    $router->middleware('auth')->get('/auditor-order-second/{slug}', static fn (): string => 'second')
        ->name('auditor.order.second');

    $snapshot = app(SnapshotBuilder::class)->capture();
    $orderedUris = array_column($snapshot['sections']['route_order'], 'uri');

    $first = array_search('auditor-order-first/{slug}', $orderedUris, true);
    $second = array_search('auditor-order-second/{slug}', $orderedUris, true);

    expect($first)->not->toBeFalse()
        ->and($second)->not->toBeFalse()
        ->and($first)->toBeLessThan($second);

    $routes = collect($snapshot['sections']['routes']);
    $secondRoute = $routes->firstWhere('uri', 'auditor-order-second/{slug}');

    expect($secondRoute['middleware'])->toContain('auth')
        ->and($secondRoute['methods'])->toContain('GET', 'HEAD');
});

it('captures resolved middleware including groups and exclusions', function () {
    $router = app('router');
    $router->aliasMiddleware('snapshot.auth', 'App\\Http\\Middleware\\Authenticate');
    $router->middlewareGroup('snapshot.group', ['snapshot.auth', 'App\\Http\\Middleware\\Audit']);
    $router->middleware('snapshot.group')->get('/snapshot-middleware', static fn (): string => 'ok')
        ->withoutMiddleware('App\\Http\\Middleware\\Audit');

    $snapshot = app(SnapshotBuilder::class)->capture();
    $route = collect($snapshot['sections']['routes'])->firstWhere('uri', 'snapshot-middleware');

    expect($route['middleware'])->toBe(['snapshot.group'])
        ->and($route['resolved_middleware'])->toBe(['App\\Http\\Middleware\\Authenticate']);
});
