<?php

declare(strict_types=1);

use LaravelAuditor\Audit\Rules\RuleRegistry;
use LaravelAuditor\Context\Collectors\AuthorizationCollector;
use LaravelAuditor\Context\Collectors\ConfigurationCollector;
use LaravelAuditor\Context\Collectors\DatabaseSchemaCollector;
use LaravelAuditor\Context\Collectors\DependenciesCollector;
use LaravelAuditor\Context\Collectors\JobsEventsSchedulesCollector;
use LaravelAuditor\Context\Collectors\MigrationsCollector;
use LaravelAuditor\Context\Collectors\ModelsCollector;
use LaravelAuditor\Context\Collectors\ProjectInfoCollector;
use LaravelAuditor\Context\Collectors\RoutesCollector;
use LaravelAuditor\Context\Collectors\TestsCollector;
use LaravelAuditor\Context\ContextRegistry;
use LaravelAuditor\Context\ProjectContext;
use LaravelAuditor\LaravelAuditor;

it('resolves Laravel Auditor services from the container', function () {
    $auditor = app(LaravelAuditor::class);

    expect($auditor->rules()->count())->toBeGreaterThanOrEqual(18);
    expect($auditor->context()->names())->toHaveCount(10);
    expect($auditor->project()->facts()['php_version'])->toBe(PHP_VERSION);
});

it('loads additional rules from configured directories', function () {
    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'laravel-auditor-extra-'.uniqid();
    mkdir($dir, 0777, true);
    file_put_contents($dir.DIRECTORY_SEPARATOR.'extra.php', '<?php return [["id" => "AUD-X-999", "name" => "Extra", "domain" => "testing", "severity" => "low", "confidence" => "low", "description" => "extra"]];');

    config(['laravel-auditor.rules' => [$dir]]);
    $this->app->forgetInstance(RuleRegistry::class);
    $this->app->forgetInstance(LaravelAuditor::class);

    expect(app(RuleRegistry::class)->find('AUD-X-999'))->not->toBeNull();

    array_map('unlink', glob($dir.DIRECTORY_SEPARATOR.'*') ?: []);
    rmdir($dir);
});

it('registers all ten context collectors', function () {
    $registry = app(ContextRegistry::class);

    expect($registry->all())->toHaveCount(10);
    expect($registry->names())->toContain('project_info', 'routes', 'models', 'migrations', 'database_schema', 'dependencies', 'configuration', 'policies_authorization', 'jobs_events_schedules', 'tests');
});

it('exposes named collectors through the registry', function () {
    $registry = app(ContextRegistry::class);

    expect($registry->has('routes'))->toBeTrue();
    expect($registry->get('routes'))->toBeInstanceOf(RoutesCollector::class);
    expect(fn () => $registry->get('nope'))->toThrow(InvalidArgumentException::class);
});

it('collects project facts from the running application', function () {
    $facts = app(ProjectInfoCollector::class)->collect();

    expect($facts['name'])->toBeString()->not->toBeEmpty();
    expect($facts['php_version'])->toBe(PHP_VERSION);
    expect($facts['laravel_version'])->toBeString();
    expect($facts['database'])->toBeString();
    expect($facts['packages'])->toBeArray()->not->toBeEmpty();
    expect($facts['source_layout'])->toBeArray();
    expect($facts['paths'])->toHaveKeys(['app', 'config', 'database', 'routes', 'resources', 'tests']);
    expect($facts['ecosystem'])->toHaveKeys(['livewire', 'filament', 'inertia', 'pest', 'phpunit', 'tailwind', 'queues', 'boost']);
    expect($facts['packages'])->toHaveKey('laravel/framework');
});

it('aggregates project facts through ProjectContext', function () {
    $facts = app(ProjectContext::class)->facts();

    expect($facts)->toHaveKeys(['name', 'environment', 'php_version', 'laravel_version', 'database', 'test_framework', 'frontend']);
    expect($facts['php_version'])->toBe(PHP_VERSION);
});

it('reports the default domain scope from ProjectContext', function () {
    $domains = app(ProjectContext::class)->domainsPresent();

    expect($domains)->toHaveCount(6);
});

it('collects the registered routes', function () {
    $data = app(RoutesCollector::class)->collect();

    expect($data['count'])->toBeGreaterThanOrEqual(1);
    expect($data['routes'][0])->toHaveKeys(['methods', 'uri', 'name', 'action', 'middleware', 'domain']);
});

it('collects composer dependencies', function () {
    $data = app(DependenciesCollector::class)->collect();

    expect($data['count'])->toBeGreaterThan(0);
    expect($data['packages'])->toHaveKey('laravel/framework');
    expect($data['requires'])->toBeArray();
    expect($data['requires_dev'])->toBeArray();
});

it('collects configuration keys safely', function () {
    $data = app(ConfigurationCollector::class)->collect();

    expect($data['count'])->toBeGreaterThanOrEqual(1);
    expect($data['keys'])->toBeArray();
    expect($data['files'])->toBeArray();
    expect($data['safe_values'])->toHaveKey('app.debug');
});

it('collects authorization context without crashing', function () {
    $data = app(AuthorizationCollector::class)->collect();

    expect($data)->toHaveKeys(['gates', 'policies', 'policy_files', 'middleware']);
    expect($data['gates'])->toBeArray();
    expect($data['policies'])->toBeArray();
    expect($data['policy_files'])->toBeArray();
});

it('collects jobs, events, and schedules safely', function () {
    $data = app(JobsEventsSchedulesCollector::class)->collect();

    expect($data)->toHaveKeys(['jobs', 'events', 'listeners', 'registered_events', 'scheduled_commands']);
    expect($data['jobs'])->toBeArray();
    expect($data['registered_events'])->toBeArray();
    expect($data['scheduled_commands'])->toBeArray();
});

it('collects migration files', function () {
    $data = app(MigrationsCollector::class)->collect();

    expect($data)->toHaveKeys(['count', 'migrations']);
    expect($data['migrations'])->toBeArray();
});

it('collects the test suite layout', function () {
    $data = app(TestsCollector::class)->collect();

    expect($data)->toHaveKeys(['framework', 'count', 'feature_tests', 'unit_tests', 'uses_pest', 'files']);
    expect($data['framework'])->toBeString();
});

it('collects the database schema read-only', function () {
    $data = app(DatabaseSchemaCollector::class)->collect();

    expect($data)->toHaveKey('available');

    if ($data['available']) {
        expect($data)->toHaveKeys(['driver', 'connection', 'tables']);
        expect($data['tables'])->toBeArray();
    } else {
        expect($data)->toHaveKey('reason');
    }
});

it('collects model metadata for the workbench app', function () {
    $data = app(ModelsCollector::class)->collect();

    expect($data)->toHaveKeys(['count', 'models']);

    expect($data['count'])->toBeGreaterThanOrEqual(1);
    expect($data['models'][0])->toHaveKeys(['class', 'table', 'fillable', 'guarded', 'casts', 'primary_key', 'relationships']);
});
