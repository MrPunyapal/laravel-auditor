<?php

declare(strict_types=1);

namespace LaravelAuditor\Context;

use LaravelAuditor\Audit\Enums\AuditDomain;
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
     * Returns the default advertised audit domain keys.
     *
     * Domain selection for a specific audit is the agent's job. This method
     * exposes the package's configured/core scope so reports and diagnostics
     * have a stable default.
     *
     * @return list<string>
     */
    public function domainsPresent(): array
    {
        $configured = array_values(array_filter(
            array_map('strval', (array) config('laravel-auditor.domains', [])),
            static fn (string $domain): bool => $domain !== '',
        ));

        if ($configured !== []) {
            return $configured;
        }

        return array_map(
            static fn (AuditDomain $domain): string => $domain->value,
            AuditDomain::core(),
        );
    }
}
