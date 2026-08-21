<?php

declare(strict_types=1);

namespace LaravelAuditor\Audit\Findings;

use JsonSerializable;
use LaravelAuditor\Audit\Enums\AuditDomain;
use LaravelAuditor\Audit\Enums\Confidence;
use LaravelAuditor\Audit\Enums\FindingStatus;
use LaravelAuditor\Audit\Enums\Severity;
use LaravelAuditor\Audit\Evidence\Evidence;
use LaravelAuditor\Audit\Evidence\EvidenceCollection;

/**
 * A structured audit finding produced by an agent applying an audit rule.
 *
 * The model is intentionally a value object: findings are created by the
 * reasoning agent and serialized into reports. The schema is extensible via
 * the metadata map so future domains do not require a breaking change.
 */
final class Finding implements JsonSerializable
{
    /**
     * @param  string  $id  Stable, unique identifier for this finding instance (e.g. F-2024-0001 or a short slug).
     * @param  string  $ruleId  The ID of the rule this finding maps to (e.g. AUD-SEC-001).
     * @param  string  $title  Short human-readable title.
     * @param  AuditDomain  $domain  The audit domain this finding belongs to.
     * @param  Severity  $severity  Impact level.
     * @param  Confidence  $confidence  How certain the finding is given available evidence.
     * @param  string  $summary  Concise description of the problem.
     * @param  string  $whyItMatters  Why the problem matters in practice.
     * @param  EvidenceCollection  $evidence  Concrete, verifiable references supporting the finding.
     * @param  list<string>  $affectedResources  Files, routes, config keys, or other resources involved.
     * @param  string|null  $symbol  Relevant class/method/route/query/config reference when available.
     * @param  string  $recommendation  What to do about it.
     * @param  string|null  $remediation  Optional step-by-step remediation guidance.
     * @param  string|null  $verificationNotes  Optional notes on how the finding was verified.
     * @param  FindingStatus  $status  Current lifecycle status.
     * @param  array<string, mixed>  $metadata  Extensible metadata for future domains.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $ruleId,
        public readonly string $title,
        public readonly AuditDomain $domain,
        public readonly Severity $severity,
        public readonly Confidence $confidence,
        public readonly string $summary,
        public readonly string $whyItMatters,
        public readonly EvidenceCollection $evidence,
        public readonly array $affectedResources = [],
        public readonly ?string $symbol = null,
        public readonly string $recommendation = '',
        public readonly ?string $remediation = null,
        public readonly ?string $verificationNotes = null,
        public readonly FindingStatus $status = FindingStatus::Open,
        public readonly array $metadata = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'rule_id' => $this->ruleId,
            'title' => $this->title,
            'domain' => $this->domain->value,
            'severity' => $this->severity->value,
            'confidence' => $this->confidence->value,
            'status' => $this->status->value,
            'summary' => $this->summary,
            'why_it_matters' => $this->whyItMatters,
            'evidence' => $this->evidence->toArray(),
            'affected_resources' => $this->affectedResources,
            'symbol' => $this->symbol,
            'recommendation' => $this->recommendation,
            'remediation' => $this->remediation,
            'verification_notes' => $this->verificationNotes,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            ruleId: (string) ($data['rule_id'] ?? ''),
            title: (string) $data['title'],
            domain: AuditDomain::from((string) $data['domain']),
            severity: Severity::from((string) $data['severity']),
            confidence: Confidence::from((string) $data['confidence']),
            summary: (string) $data['summary'],
            whyItMatters: (string) $data['why_it_matters'],
            evidence: EvidenceCollection::fromIterable(array_map(
                static fn (array $item): Evidence => Evidence::fromArray($item),
                (array) ($data['evidence'] ?? []),
            )),
            affectedResources: array_values(array_map('strval', (array) ($data['affected_resources'] ?? []))),
            symbol: isset($data['symbol']) ? (string) $data['symbol'] : null,
            recommendation: (string) ($data['recommendation'] ?? ''),
            remediation: isset($data['remediation']) ? (string) $data['remediation'] : null,
            verificationNotes: isset($data['verification_notes']) ? (string) $data['verification_notes'] : null,
            status: FindingStatus::from((string) ($data['status'] ?? 'open')),
            metadata: (array) ($data['metadata'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
