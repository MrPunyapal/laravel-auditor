<?php

declare(strict_types=1);

namespace LaravelAuditor\Audit\Reports;

use JsonSerializable;
use LaravelAuditor\Audit\Enums\Severity;
use LaravelAuditor\Audit\Findings\Finding;
use LaravelAuditor\Audit\Findings\FindingCollection;

/**
 * A complete audit report: project context, domains run, and findings.
 *
 * The model is renderer-agnostic. Renderers (Markdown, JSON, CLI text) are
 * separate so the same report can be consumed by humans and machines.
 */
final class AuditReport implements JsonSerializable
{
    /**
     * @param  array<string, mixed>  $project  Deterministic project facts (versions, packages, architecture signals).
     * @param  list<string>  $domainsRun  Audit domain keys that were actually scoped and run.
     * @param  FindingCollection  $findings  The findings produced by the audit.
     * @param  array<string, mixed>  $meta  Audit metadata such as generator, timestamp, or config.
     */
    public function __construct(
        public readonly array $project,
        public readonly array $domainsRun,
        public readonly FindingCollection $findings,
        public readonly array $meta = [],
    ) {}

    public function totalFindings(): int
    {
        return $this->findings->count();
    }

    /**
     * @return array<string, int>
     */
    public function countsBySeverity(): array
    {
        return $this->findings->countsBySeverity();
    }

    /**
     * @return array<string, int>
     */
    public function countsByDomain(): array
    {
        return $this->findings->countsByDomain();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function keyRisks(): array
    {
        return array_map(
            fn (Finding $finding): array => $finding->toArray(),
            $this->findings->atLeast(Severity::High)->sorted()->all(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'meta' => $this->meta,
            'project' => $this->project,
            'domains_run' => $this->domainsRun,
            'summary' => [
                'total_findings' => $this->totalFindings(),
                'counts_by_severity' => $this->countsBySeverity(),
                'counts_by_domain' => $this->countsByDomain(),
            ],
            'key_risks' => $this->keyRisks(),
            'findings' => $this->findings->sorted()->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
