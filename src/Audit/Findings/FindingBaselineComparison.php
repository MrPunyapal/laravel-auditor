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
     * @param  list<array{id: string, rule_id: string, title: string, domain: string, severity: string}>  $absent
     * @param  array<string, 'new'|'unchanged'|'updated'>  $states
     */
    public function __construct(
        public readonly array $new,
        public readonly array $persistent,
        public readonly array $absent,
        private readonly array $states,
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
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'summary' => $this->summary(),
            'new' => array_map(self::currentSummary(...), $this->new),
            'persistent' => array_map(self::currentSummary(...), $this->persistent),
            'absent' => $this->absent,
            'notes' => [
                'An absent entry means its id was not present in the current findings file; this does not prove the finding was fixed.',
                'Compare files produced with the same audit scope before interpreting missing entries.',
            ],
        ];
    }

    /**
     * @return array{id: string, rule_id: string, title: string, domain: string, severity: string}
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
