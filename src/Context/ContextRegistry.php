<?php

declare(strict_types=1);

namespace LaravelAuditor\Context;

use InvalidArgumentException;
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

/**
 * Registry of the context collectors exposed by the package.
 */
final class ContextRegistry
{
    /**
     * @var array<string, ContextCollector>|null
     */
    private ?array $collectors = null;

    public function __construct(
        private readonly ProjectInfoCollector $projectInfo,
        private readonly RoutesCollector $routes,
        private readonly ModelsCollector $models,
        private readonly MigrationsCollector $migrations,
        private readonly DatabaseSchemaCollector $databaseSchema,
        private readonly DependenciesCollector $dependencies,
        private readonly ConfigurationCollector $configuration,
        private readonly AuthorizationCollector $authorization,
        private readonly JobsEventsSchedulesCollector $jobsEventsSchedules,
        private readonly TestsCollector $tests,
        private readonly SubsystemsCollector $subsystems,
    ) {}

    /**
     * @return array<string, ContextCollector> Keyed by collector name.
     */
    public function all(): array
    {
        return $this->collectors ??= [
            $this->projectInfo->name() => $this->projectInfo,
            $this->routes->name() => $this->routes,
            $this->models->name() => $this->models,
            $this->migrations->name() => $this->migrations,
            $this->databaseSchema->name() => $this->databaseSchema,
            $this->dependencies->name() => $this->dependencies,
            $this->configuration->name() => $this->configuration,
            $this->authorization->name() => $this->authorization,
            $this->jobsEventsSchedules->name() => $this->jobsEventsSchedules,
            $this->tests->name() => $this->tests,
            $this->subsystems->name() => $this->subsystems,
        ];
    }

    public function has(string $name): bool
    {
        return isset($this->all()[$name]);
    }

    public function get(string $name): ContextCollector
    {
        return $this->all()[$name] ?? throw new InvalidArgumentException("Unknown context collector [{$name}].");
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->all());
    }
}
