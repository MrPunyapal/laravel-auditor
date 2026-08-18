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
use LaravelAuditor\Context\Collectors\SubsystemsCollector;
use LaravelAuditor\Context\Collectors\TestsCollector;
use LaravelAuditor\Context\ContextRegistry;
use LaravelAuditor\Context\ProjectContext;
use LaravelAuditor\LaravelAuditor;

it('resolves Laravel Auditor services from the container', function () {
    $auditor = app(LaravelAuditor::class);

    expect($auditor->rules()->count())->toBeGreaterThanOrEqual(18);
    expect($auditor->context()->names())->toHaveCount(11);
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

    expect($registry->all())->toHaveCount(11);
    expect($registry->names())->toContain('project_info', 'routes', 'models', 'migrations', 'database_schema', 'dependencies', 'configuration', 'policies_authorization', 'jobs_events_schedules', 'tests', 'subsystems');
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
    expect($facts['packages']['laravel/framework']['dev'])->toBeBool();
    expect($facts['packages']['pestphp/pest']['dev'])->toBeTrue();
    expect($facts['architecture_signals']['policies'])->toBeTrue();
    expect($facts['architecture_signals']['console'])->toBeTrue();
    expect($facts['source_layout']['app_files'])->toBeGreaterThan(0);
    expect($facts['source_layout']['migrations'])->toBeGreaterThan(0);
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
    expect($data['composer_audit'])->toHaveKeys(['available', 'reason']);
    expect($data['composer_audit']['available'])->toBeFalse();
});

it('keeps composer audit and test listing off in the packaged defaults', function () {
    $defaults = require dirname(__DIR__, 2).'/config/laravel-auditor.php';

    expect($defaults['context']['composer_audit'])->toBeFalse();
    expect($defaults['context']['test_listing'])->toBeFalse();
});

it('reports the composer audit disabled by configuration', function () {
    config(['laravel-auditor.context.composer_audit' => false]);

    $audit = app(DependenciesCollector::class)->collect()['composer_audit'];

    expect($audit['reason'])->toBe('composer audit is disabled by configuration');
});

it('collects configuration keys safely', function () {
    $data = app(ConfigurationCollector::class)->collect();

    expect($data['count'])->toBeGreaterThanOrEqual(1);
    expect($data['keys'])->toBeArray();
    expect($data['keys'])->toContain('app', 'app.debug');
    expect($data['keys'])->not->toContain('app.aliases.App');
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
    expect($data['count'])->toBeGreaterThanOrEqual(1);
    expect(implode("\n", array_column($data['migrations'], 'file')))->toContain('create_posts_table');
});

it('collects the test suite layout', function () {
    $data = app(TestsCollector::class)->collect();

    expect($data)->toHaveKeys(['framework', 'count', 'feature_tests', 'unit_tests', 'count_source', 'file_count', 'feature_file_count', 'unit_file_count', 'uses_pest', 'files']);
    expect($data['framework'])->toBeString();
    expect($data['count'])->toBeInt();
    expect($data['file_count'])->toBeInt();
    expect($data['count_source'])->toBe('file-count');
    expect($data['files'])->each->not->toContain('CreatesApplication.php');
    expect($data['files'])->each->not->toContain('Pest.php');
});

it('parses the test runner listing into feature/unit/total counts', function () {
    $output = implode("\n", [
        'PHPUnit 10.5 by Sebastian Bergmann and contributors.',
        '',
        'Available tests:',
        ' - P\Tests\Feature\Http\Controllers\PostControllerTest::__pest_evaluable_can_create_post',
        ' - P\Tests\Feature\Http\Controllers\PostControllerTest::__pest_evaluable_can_delete_post',
        ' - P\Tests\Unit\Actions\CreatePostActionTest::__pest_evaluable_it_creates_a_post',
        ' - P\Tests\Unit\Actions\DeletePostActionTest::__pest_evaluable_it_deletes_a_post',
        ' - P\Tests\Unit\Support\QueryResolverTest::__pest_evaluable_it_can_resolve_sort_query"([1], [2])"',
    ]);

    $parsed = TestsCollector::parseTestListing($output);

    expect($parsed)->toBe([
        'total' => 5,
        'feature' => 2,
        'unit' => 3,
    ]);
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

it('inventories subsystems for a bounded audit', function () {
    $data = app(SubsystemsCollector::class)->collect();

    expect($data['count'])->toBeGreaterThanOrEqual(3);
    expect($data['subsystems'][0])->toHaveKeys(['id', 'name', 'boundary', 'files', 'collectors', 'status']);
    expect(array_column($data['subsystems'], 'id'))->toContain('HTTP', 'MDL', 'TST');

    $http = collect($data['subsystems'])->firstWhere('id', 'HTTP');
    $files = $http['files'] ?? [];

    expect($files)->not->toBeEmpty();
    expect($files)->each->not->toBe('');
    expect(implode("\n", $files))->toContain('PostController.php');
});

it('collects model metadata for the workbench app', function () {
    $data = app(ModelsCollector::class)->collect();

    expect($data)->toHaveKeys(['count', 'models']);

    expect($data['count'])->toBeGreaterThanOrEqual(1);
    expect($data['models'][0])->toHaveKeys(['class', 'table', 'fillable', 'guarded', 'casts', 'primary_key', 'relationships']);
});

it('skips models that cannot be instantiated without crashing the collector', function () {
    $collector = app(ModelsCollector::class);

    $data = $collector->collect();

    expect($data)->toHaveKeys(['count', 'models']);
    expect($data['count'])->toBeInt();
});
