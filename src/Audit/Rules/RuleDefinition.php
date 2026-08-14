<?php

declare(strict_types=1);

namespace LaravelAuditor\Audit\Rules;

use JsonSerializable;
use LaravelAuditor\Audit\Enums\AuditDomain;
use LaravelAuditor\Audit\Enums\Confidence;
use LaravelAuditor\Audit\Enums\Severity;

/**
 * The stable, reviewable definition of an audit rule.
 *
 * A rule describes what to look for, when it applies, what evidence is
 * required, how to avoid false positives, and how to fix the problem.
 * Rules are metadata for the reasoning agent; they are not executable checks.
 */
final class RuleDefinition implements JsonSerializable
{
    /**
     * @param  string  $id  Stable unique rule ID, e.g. AUD-SEC-001.
     * @param  string  $name  Short human-readable rule name.
     * @param  AuditDomain  $domain  The audit domain this rule belongs to.
     * @param  Severity  $severity  Typical severity of a confirmed instance of this rule.
     * @param  Confidence  $confidence  Expected confidence a properly-evidenced finding carries.
     * @param  string  $description  What the rule checks for.
     * @param  string  $whyItMatters  Why the pattern matters.
     * @param  string  $recommendation  What to do about it.
     * @param  list<string>  $evidence  Evidence requirements for a finding that applies this rule.
     * @param  list<string>  $falsePositiveConsiderations  Situations where the rule should not fire.
     * @param  list<string>  $references  Docs, blog posts, or code references.
     * @param  array<string, mixed>  $applicability  Constraints such as Laravel version ranges or ecosystem packages.
     * @param  array<string, mixed>  $metadata  Extensible metadata.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly AuditDomain $domain,
        public readonly Severity $severity,
        public readonly Confidence $confidence,
        public readonly string $description,
        public readonly string $whyItMatters,
        public readonly string $recommendation,
        public readonly array $evidence = [],
        public readonly array $falsePositiveConsiderations = [],
        public readonly array $references = [],
        public readonly array $applicability = [],
        public readonly array $metadata = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'domain' => $this->domain->value,
            'severity' => $this->severity->value,
            'confidence' => $this->confidence->value,
            'description' => $this->description,
            'why_it_matters' => $this->whyItMatters,
            'recommendation' => $this->recommendation,
            'evidence' => $this->evidence,
            'false_positive_considerations' => $this->falsePositiveConsiderations,
            'references' => $this->references,
            'applicability' => $this->applicability,
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
            name: (string) $data['name'],
            domain: AuditDomain::from((string) $data['domain']),
            severity: Severity::from((string) $data['severity']),
            confidence: Confidence::from((string) ($data['confidence'] ?? 'high')),
            description: (string) ($data['description'] ?? ''),
            whyItMatters: (string) ($data['why_it_matters'] ?? ''),
            recommendation: (string) ($data['recommendation'] ?? ''),
            evidence: array_values(array_map('strval', (array) ($data['evidence'] ?? []))),
            falsePositiveConsiderations: array_values(array_map('strval', (array) ($data['false_positive_considerations'] ?? []))),
            references: array_values(array_map('strval', (array) ($data['references'] ?? []))),
            applicability: (array) ($data['applicability'] ?? []),
            metadata: (array) ($data['metadata'] ?? []),
        );
    }

    /**
     * Whether this rule applies given the installed Laravel version and packages.
     *
     * Unknown versions skip the version constraint rather than excluding the rule.
     *
     * @param  array<int|string, string>|array<string, mixed>  $installedPackages
     */
    public function applies(?string $laravelVersion = null, array $installedPackages = []): bool
    {
        $min = $this->applicability['laravel_min'] ?? null;
        $max = $this->applicability['laravel_max'] ?? null;
        $packages = $this->applicability['packages'] ?? [];

        if (is_string($min) && $laravelVersion !== null && version_compare($this->normalizeVersion($laravelVersion), $min, '<')) {
            return false;
        }

        if (is_string($max) && $laravelVersion !== null && version_compare($this->normalizeVersion($laravelVersion), $max, '>')) {
            return false;
        }

        foreach ((array) $packages as $package) {
            if (! is_string($package) || $package === '') {
                continue;
            }

            if (! $this->packageIsInstalled($package, $installedPackages)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private function normalizeVersion(string $version): string
    {
        return ltrim($version, 'vV');
    }

    /**
     * @param  array<int|string, mixed>  $installedPackages
     */
    private function packageIsInstalled(string $package, array $installedPackages): bool
    {
        if (array_key_exists($package, $installedPackages)) {
            return true;
        }

        return in_array($package, $installedPackages, true);
    }
}
