<?php

declare(strict_types=1);

namespace LaravelAuditor;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use LaravelAuditor\Audit\Domains\DomainRegistry;
use LaravelAuditor\Audit\Rules\RuleRegistry;
use LaravelAuditor\Console\Commands\AuditorCiCommand;
use LaravelAuditor\Console\Commands\AuditorContextCommand;
use LaravelAuditor\Console\Commands\AuditorInstallCommand;
use LaravelAuditor\Console\Commands\AuditorMcpCommand;
use LaravelAuditor\Console\Commands\AuditorReportCommand;
use LaravelAuditor\Console\Commands\AuditorRulesCommand;
use LaravelAuditor\Console\Commands\AuditorStatusCommand;
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
use LaravelAuditor\MCP\Boost\BoostMcpRegistrar;
use LaravelAuditor\MCP\McpToolRegistry;
use LaravelAuditor\Support\BoostDetector;

class LaravelAuditorServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-auditor.php', 'laravel-auditor');

        $this->app->singleton(BoostDetector::class);

        $this->app->singleton(LaravelAuditor::class);
        $this->app->alias(LaravelAuditor::class, 'laravel-auditor');

        $this->registerRuleRegistry();
        $this->registerDomainRegistry();
        $this->registerContext();
        $this->registerMcp();
        $this->registerProjectContext();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/laravel-auditor.php' => config_path('laravel-auditor.php'),
        ], ['laravel-auditor', 'laravel-auditor-config']);

        $this->publishes(
            $this->agentResourcePublishMap(),
            ['laravel-auditor', 'laravel-auditor-resources'],
        );

        $this->publishes([
            __DIR__.'/../resources/auditor/schema' => base_path($this->resourcesTarget().'/schema'),
        ], ['laravel-auditor', 'laravel-auditor-schema']);

        $this->publishes([
            __DIR__.'/../resources/auditor/examples' => base_path($this->resourcesTarget().'/examples'),
        ], ['laravel-auditor', 'laravel-auditor-examples']);

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditorInstallCommand::class,
                AuditorStatusCommand::class,
                AuditorRulesCommand::class,
                AuditorReportCommand::class,
                AuditorContextCommand::class,
                AuditorCiCommand::class,
                AuditorMcpCommand::class,
            ]);
        }

        $this->app->make(BoostMcpRegistrar::class)->register();
    }

    private function registerRuleRegistry(): void
    {
        $this->app->singleton(RuleRegistry::class, static function (Application $app): RuleRegistry {
            $configured = array_values(array_filter(
                array_map('strval', (array) config('laravel-auditor.rules', [])),
                static fn (string $path): bool => $path !== '',
            ));

            return new RuleRegistry(
                $app->make(Filesystem::class),
                [
                    __DIR__.'/../resources/auditor/rules',
                    ...$configured,
                ],
            );
        });
    }

    private function registerDomainRegistry(): void
    {
        $this->app->singleton(DomainRegistry::class);
    }

    private function registerProjectContext(): void
    {
        $this->app->singleton(ProjectContext::class);
    }

    private function registerContext(): void
    {
        $this->app->singleton(ContextRegistry::class, static function (Application $app): ContextRegistry {
            return new ContextRegistry(
                projectInfo: $app->make(ProjectInfoCollector::class),
                routes: $app->make(RoutesCollector::class),
                models: $app->make(ModelsCollector::class),
                migrations: $app->make(MigrationsCollector::class),
                databaseSchema: $app->make(DatabaseSchemaCollector::class),
                dependencies: $app->make(DependenciesCollector::class),
                configuration: $app->make(ConfigurationCollector::class),
                authorization: $app->make(AuthorizationCollector::class),
                jobsEventsSchedules: $app->make(JobsEventsSchedulesCollector::class),
                tests: $app->make(TestsCollector::class),
                subsystems: $app->make(SubsystemsCollector::class),
            );
        });

        $this->app->singleton(ProjectInfoCollector::class);
        $this->app->singleton(RoutesCollector::class, fn (Application $app) => new RoutesCollector($app->make('router')));
        $this->app->singleton(ModelsCollector::class);
        $this->app->singleton(MigrationsCollector::class);
        $this->app->singleton(DatabaseSchemaCollector::class);
        $this->app->singleton(DependenciesCollector::class);
        $this->app->singleton(ConfigurationCollector::class);
        $this->app->singleton(AuthorizationCollector::class);
        $this->app->singleton(JobsEventsSchedulesCollector::class);
        $this->app->singleton(TestsCollector::class);
        $this->app->singleton(SubsystemsCollector::class);
    }

    private function registerMcp(): void
    {
        $this->app->singleton(McpToolRegistry::class);
        $this->app->singleton(BoostMcpRegistrar::class);
    }

    /**
     * @return array<string, string>
     */
    private function agentResourcePublishMap(): array
    {
        $base = __DIR__.'/../resources/auditor';
        $target = $this->resourcesTarget();

        return [
            $base.'/skills' => base_path($target.'/skills'),
            $base.'/guidelines' => base_path($target.'/guidelines'),
            $base.'/schema' => base_path($target.'/schema'),
            $base.'/examples' => base_path($target.'/examples'),
        ];
    }

    private function resourcesTarget(): string
    {
        $target = trim((string) config('laravel-auditor.resources_target', '.ai'), '/\\');

        return $target !== '' ? $target : '.ai';
    }
}
