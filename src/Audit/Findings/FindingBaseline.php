<?php

declare(strict_types=1);

namespace LaravelAuditor\Audit\Findings;

use JsonException;
use LaravelAuditor\Audit\Enums\AuditDomain;
use LaravelAuditor\Audit\Enums\Severity;
use RuntimeException;

/**
 * A deterministic, versioned snapshot of finding identities and display fields.
 *
 * Evidence and summaries are intentionally omitted so a baseline does not copy
 * potentially sensitive finding details that are not needed for comparison.
 */
final class FindingBaseline
{
    public const string FORMAT = 'laravel-auditor.findings-baseline';

    public const int VERSION = 1;

    /**
     * @param  array<string, array{id: string, rule_id: string, title: string, domain: string, severity: string}>  $findings
     */
    private function __construct(
        private readonly array $findings,
    ) {}

    public static function fromFindings(FindingCollection $findings): self
    {
        $entries = [];

        foreach ($findings->all() as $finding) {
            self::assertUniqueId($entries, $finding->id);
            $entries[$finding->id] = [
                'id' => $finding->id,
                'rule_id' => $finding->ruleId,
                'title' => $finding->title,
                'domain' => $finding->domain->value,
                'severity' => $finding->severity->value,
            ];
        }

        ksort($entries, SORT_STRING);

        return new self($entries);
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        if (array_is_list($data) || ($data['format'] ?? null) !== self::FORMAT) {
            throw new RuntimeException('Unsupported findings baseline format.');
        }

        if (($data['version'] ?? null) !== self::VERSION) {
            throw new RuntimeException('Unsupported findings baseline version.');
        }

        if (array_diff(array_keys($data), ['format', 'version', 'findings']) !== []) {
            throw new RuntimeException('Findings baseline contains unsupported fields.');
        }

        $findings = $data['findings'] ?? null;

        if (! is_array($findings) || ! array_is_list($findings)) {
            throw new RuntimeException('Findings baseline must contain a findings array.');
        }

        $entries = [];

        foreach ($findings as $index => $finding) {
            if (! is_array($finding)) {
                throw new RuntimeException("Findings baseline item [{$index}] must be an object.");
            }

            if (array_diff(array_keys($finding), ['id', 'rule_id', 'title', 'domain', 'severity']) !== []) {
                throw new RuntimeException("Findings baseline item [{$index}] contains unsupported fields.");
            }

            $id = $finding['id'] ?? null;
            $ruleId = $finding['rule_id'] ?? null;
            $title = $finding['title'] ?? null;
            $domain = $finding['domain'] ?? null;
            $severity = $finding['severity'] ?? null;

            if (! is_string($id) || trim($id) === ''
                || ! is_string($ruleId)
                || ! is_string($title)
                || ! is_string($domain) || AuditDomain::tryFrom($domain) === null
                || ! is_string($severity) || Severity::tryFrom($severity) === null) {
                throw new RuntimeException("Findings baseline item [{$index}] is invalid.");
            }

            self::assertUniqueId($entries, $id);
            $entries[$id] = [
                'id' => $id,
                'rule_id' => $ruleId,
                'title' => $title,
                'domain' => $domain,
                'severity' => $severity,
            ];
        }

        ksort($entries, SORT_STRING);

        return new self($entries);
    }

    public static function load(string $path): self
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("Findings baseline [{$path}] is missing or unreadable.");
        }

        $contents = file_get_contents($path);

        if (! is_string($contents)) {
            throw new RuntimeException("Findings baseline [{$path}] could not be read.");
        }

        try {
            $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Invalid findings baseline JSON: '.$e->getMessage(), previous: $e);
        }

        if (! is_array($data)) {
            throw new RuntimeException('Findings baseline must contain a JSON object.');
        }

        return self::fromArray($data);
    }

    public function compare(FindingCollection $current): FindingBaselineComparison
    {
        $new = [];
        $persistent = [];
        $states = [];
        $seen = [];

        foreach ($current->all() as $finding) {
            self::assertUniqueId($seen, $finding->id);
            $seen[$finding->id] = true;

            if (! isset($this->findings[$finding->id])) {
                $new[] = $finding;
                $states[$finding->id] = 'new';

                continue;
            }

            $persistent[] = $finding;
            $baseline = $this->findings[$finding->id];
            $states[$finding->id] = $baseline['title'] === $finding->title
                && $baseline['rule_id'] === $finding->ruleId
                && $baseline['domain'] === $finding->domain->value
                && $baseline['severity'] === $finding->severity->value
                ? 'unchanged'
                : 'updated';
        }

        $absent = array_values(array_filter(
            $this->findings,
            static fn (array $finding): bool => ! isset($seen[$finding['id']]),
        ));

        usort($new, static fn (Finding $a, Finding $b): int => strcmp($a->id, $b->id));
        usort($persistent, static fn (Finding $a, Finding $b): int => strcmp($a->id, $b->id));

        return new FindingBaselineComparison($new, $persistent, $absent, $states);
    }

    /**
     * @return array{format: string, version: int, findings: list<array{id: string, rule_id: string, title: string, domain: string, severity: string}>}
     */
    public function toArray(): array
    {
        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'findings' => array_values($this->findings),
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
    }

    /**
     * @param  array<string, mixed>  $entries
     */
    private static function assertUniqueId(array $entries, string $id): void
    {
        if (trim($id) === '') {
            throw new RuntimeException('Baseline comparison requires unique, non-empty finding ids.');
        }

        if (array_key_exists($id, $entries)) {
            throw new RuntimeException("Baseline comparison requires unique, non-empty finding ids; [{$id}] is duplicated.");
        }
    }
}
