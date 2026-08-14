<?php

declare(strict_types=1);

namespace LaravelAuditor\Audit\Reports;

use LaravelAuditor\Audit\Enums\AuditDomain;
use LaravelAuditor\Audit\Enums\Priority;
use LaravelAuditor\Audit\Findings\Finding;

/**
 * Renders an AuditReport as human-friendly Markdown.
 */
final class MarkdownReportRenderer
{
    public function render(AuditReport $report): string
    {
        $lines = [];

        $lines[] = '# Laravel Auditor Report';
        $lines[] = '';

        if (($generated = $report->meta['generated_at'] ?? null) !== null) {
            $lines[] = "**Generated:** {$generated}";
            $lines[] = '';
        }

        $lines[] = '## Project';
        $lines[] = '';
        $lines[] = $this->renderProject($report);
        $lines[] = '';

        $lines[] = '## Summary';
        $lines[] = '';
        $lines[] = sprintf('**Findings:** %d', $report->totalFindings());
        $lines[] = '';
        $lines[] = $this->renderSeverityCounts($report);
        $lines[] = '';
        $lines[] = $this->renderDomainCounts($report);
        $lines[] = '';
        $lines[] = $this->renderPriorityTiers($report);
        $lines[] = '';

        $lines[] = '## Domains Audited';
        $lines[] = '';
        $lines[] = $this->renderDomainsRun($report);
        $lines[] = '';

        $lines[] = '## Findings';
        $lines[] = '';

        if ($report->findings->isEmpty()) {
            $lines[] = 'No findings were produced for this audit.';
        } else {
            foreach ($report->findings->sorted()->all() as $finding) {
                $lines[] = $this->renderFinding($finding);
            }
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    private function renderProject(AuditReport $report): string
    {
        $project = $report->project;

        if ($project === []) {
            return 'No project facts were collected.';
        }

        $lines = [];

        foreach ($project as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', array_map('strval', $value));
            }

            $lines[] = sprintf('- **%s:** %s', str_replace('_', ' ', (string) $key), (string) $value);
        }

        return implode(PHP_EOL, $lines);
    }

    private function renderSeverityCounts(AuditReport $report): string
    {
        $rows = [];

        foreach ($report->countsBySeverity() as $severity => $count) {
            $rows[] = sprintf('| %s | %d |', ucfirst((string) $severity), $count);
        }

        return implode(PHP_EOL, [
            '| Severity | Count |',
            '| --- | --- |',
            ...$rows,
        ]);
    }

    private function renderDomainCounts(AuditReport $report): string
    {
        $rows = [];

        foreach ($report->countsByDomain() as $domain => $count) {
            $rows[] = sprintf('| %s | %d |', AuditDomain::from((string) $domain)->label(), $count);
        }

        return implode(PHP_EOL, [
            '| Domain | Count |',
            '| --- | --- |',
            ...$rows,
        ]);
    }

    private function renderPriorityTiers(AuditReport $report): string
    {
        $lines = [
            '## Priority synthesis',
            '',
            sprintf('**Final partition:** %d unique recommendation(s). Every promoted ID appears exactly once.', $report->totalFindings()),
            '',
        ];

        foreach ($report->priorityTiers() as $tier => $ids) {
            $priority = Priority::from($tier);
            $lines[] = sprintf(
                '- **%s** (%d): %s',
                $priority->label(),
                count($ids),
                $ids === [] ? 'none' : implode(', ', $ids),
            );
        }

        return implode(PHP_EOL, $lines);
    }

    private function renderDomainsRun(AuditReport $report): string
    {
        if ($report->domainsRun === []) {
            return 'No domains were selected.';
        }

        return implode(PHP_EOL, array_map(
            static fn (string $domain): string => sprintf('- %s', AuditDomain::from($domain)->label()),
            $report->domainsRun,
        ));
    }

    private function renderFinding(Finding $finding): string
    {
        $lines = [];

        $lines[] = sprintf(
            '### [%s] %s `%s`',
            strtoupper($finding->severity->value),
            $finding->title,
            $finding->id,
        );
        $lines[] = '';

        $lines[] = sprintf('**Rule:** `%s` — %s', $finding->ruleId, $finding->domain->label());
        $lines[] = sprintf('**Severity:** %s', $finding->severity->label());
        $lines[] = sprintf('**Confidence:** %s', $finding->confidence->label());
        $lines[] = sprintf('**Status:** %s', $finding->status->label());
        $lines[] = '';

        $lines[] = '**Summary**';
        $lines[] = '';
        $lines[] = $finding->summary;
        $lines[] = '';

        $lines[] = '**Why it matters**';
        $lines[] = '';
        $lines[] = $finding->whyItMatters;
        $lines[] = '';

        if (! $finding->evidence->isEmpty()) {
            $lines[] = '**Evidence**';
            $lines[] = '';

            foreach ($finding->evidence->all() as $evidence) {
                $ref = $evidence->reference;

                if ($evidence->line !== null) {
                    $ref .= ':'.$evidence->line;

                    if ($evidence->endLine !== null && $evidence->endLine !== $evidence->line) {
                        $ref .= '-'.$evidence->endLine;
                    }
                }

                $lines[] = sprintf('- `%s` — %s', $evidence->type, $ref);

                if ($evidence->detail !== null) {
                    $lines[] = '  - '.$evidence->detail;
                }
            }

            $lines[] = '';
        }

        if ($finding->affectedResources !== []) {
            $lines[] = '**Affected resources**';
            $lines[] = '';
            foreach ($finding->affectedResources as $resource) {
                $lines[] = sprintf('- `%s`', $resource);
            }
            $lines[] = '';
        }

        if ($finding->recommendation !== '') {
            $lines[] = '**Recommendation**';
            $lines[] = '';
            $lines[] = $finding->recommendation;
            $lines[] = '';
        }

        if ($finding->remediation !== null) {
            $lines[] = '**Remediation**';
            $lines[] = '';
            $lines[] = $finding->remediation;
            $lines[] = '';
        }

        if ($finding->verificationNotes !== null) {
            $lines[] = '**Verification notes**';
            $lines[] = '';
            $lines[] = $finding->verificationNotes;
            $lines[] = '';
        }

        return implode(PHP_EOL, $lines);
    }
}
