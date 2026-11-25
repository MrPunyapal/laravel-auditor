<?php

declare(strict_types=1);

namespace LaravelAuditor\Audit\Findings;

/**
 * Compares a current findings file with a saved baseline by stable finding id.
 */
final class FindingBaselineComparison
{
    /**
     * @param  list<Finding>  $new
     * @param  list<Finding>  $persistent
     * @param  list<array{id: string, rule_id: string, title: string, domain: string, severity: string, status?: string}>  $absent
     * @param  array<string, 'new'|'unchanged'|'updated'>  $states
     * @param  list<array{id: string, previous_severity: string, current_severity: string, previous_status: string|null, current_status: string, severity_increased: bool, reopened: bool}>  $regressions
     */
    public function __construct(
        public readonly array $new,
        public readonly array $persistent,
        public readonly array $absent,
        private readonly array $states,
        public readonly array $regressions = [],
        public readonly bool $statusHistoryAvailable = false,
    ) {}

    /**
     * @return array{new: int, persistent: int, absent: int}
     */
    public function summary(): array
    {
        return [
            'new' => count($this->new),
            'persistent' => count($this->persistent),
            'absent' => count($this->absent),
        ];
    }

    public function stateFor(string $id): ?string
    {
        return $this->states[$id] ?? null;
    }

    /**
     * @return list<Finding>
     */
    public function regressionCandidates(): array
    {
        $ids = array_fill_keys(array_column($this->regressions, 'id'), true);

        return [...$this->new, ...array_values(array_filter(
            $this->persistent,
            static fn (Finding $finding): bool => isset($ids[$finding->id]),
        ))];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'summary' => $this->summary(),
            'new' => array_map(self::currentSummary(...), $this->new),
            'persistent' => array_map(self::currentSummary(...), $this->persistent),
            'absent' => $this->absent,
            'status_history_available' => $this->statusHistoryAvailable,
            'regressions' => $this->regressions,
            'states' => array_map(fn (Finding $finding): array => [
                'id' => $finding->id,
                'state' => $this->stateFor($finding->id),
            ], [...$this->new, ...$this->persistent]),
            'notes' => [
                'An absent entry means its id was not present in the current findings file; this does not prove the finding was fixed.',
                'Compare files produced with the same audit scope before interpreting missing entries.',
                $this->statusHistoryAvailable
                    ? 'Reopened means a previously non-open finding is now open; producer status is not proof of remediation.'
                    : 'Historical status is unknown for version 1 baselines; reopened findings cannot be determined.',
            ],
        ];
    }

    /**
     * @return array{id: string, rule_id: string, title: string, domain: string, severity: string, status?: string}
     */
    private static function currentSummary(Finding $finding): array
    {
        return [
            'id' => $finding->id,
            'rule_id' => $finding->ruleId,
            'title' => $finding->title,
            'domain' => $finding->domain->value,
            'severity' => $finding->severity->value,
        ];
    }
}
