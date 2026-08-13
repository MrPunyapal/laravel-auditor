<?php

declare(strict_types=1);

namespace LaravelAuditor\Context;

use LaravelAuditor\Context\Collectors\ProjectInfoCollector;

/**
 * Aggregates deterministic project context for reporting and diagnostics.
 */
final class ProjectContext
{
    public function __construct(
        private readonly ProjectInfoCollector $projectInfo,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function facts(): array
    {
        $info = $this->projectInfo->collect();

        return [
            'name' => $info['name'] ?? 'Unknown',
            'environment' => $info['environment'] ?? null,
            'php_version' => $info['php_version'] ?? PHP_VERSION,
            'laravel_version' => $info['laravel_version'] ?? null,
            'database' => $info['database'] ?? null,
            'test_framework' => $info['test_framework'] ?? null,
            'frontend' => $info['frontend'] ?? [],
        ];
    }

    /**
     * Returns the list of audit domain keys that are relevant given the
     * project facts. This is a deterministic, conservative default scope.
     *
     * @return list<string>
     */
    public function domainsPresent(): array
    {
        $info = $this->projectInfo->collect();

        $domains = [
            'security',
            'performance',
            'architecture',
            'database',
            'testing',
            'conventions',
        ];

        $signals = (array) ($info['source_layout'] ?? []);

        // If the application has essentially no source code, drop domains
        // that would have nothing to inspect.
        $appFiles = (int) ($signals['app_files'] ?? 0);

        if ($appFiles === 0) {
            return [];
        }

        return $domains;
    }
}
