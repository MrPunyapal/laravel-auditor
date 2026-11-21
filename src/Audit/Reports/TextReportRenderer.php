<?php

declare(strict_types=1);

namespace LaravelAuditor\Audit\Reports;

use LaravelAuditor\Audit\Findings\Finding;

/**
 * Renders an AuditReport as compact plain text for terminal output.
 */
final class TextReportRenderer
{
    public function render(AuditReport $report): string
    {
        $lines = [];

        $lines[] = 'Laravel Auditor Report';
        $lines[] = str_repeat('-', 60);
        $lines[] = '';

        if (($generated = $report->meta['generated_at'] ?? null) !== null) {
            $lines[] = 'Generated: '.$generated;
            $lines[] = '';
        }

        $lines[] = sprintf('Project: %s', $report->project['name'] ?? 'Unknown');
        $lines[] = sprintf('Laravel: %s | PHP: %s', $report->project['laravel_version'] ?? '?', $report->project['php_version'] ?? '?');
        $lines[] = '';

        $lines[] = sprintf('Findings: %d', $report->totalFindings());

        foreach ($report->countsBySeverity() as $severity => $count) {
            if ($count > 0) {
                $lines[] = sprintf('  %-8s %d', strtoupper((string) $severity), $count);
            }
        }

        if ($report->baseline !== null) {
            $summary = $report->baseline->summary();
            $lines[] = '';
            $lines[] = sprintf('Baseline: %d new, %d persistent, %d absent from current findings file.', $summary['new'], $summary['persistent'], $summary['absent']);
            $lines[] = 'Absence does not prove a finding is fixed; compare runs with the same full audit scope.';
        }

        $lines[] = '';

        foreach ($report->findings->sorted()->all() as $finding) {
            $lines[] = $this->renderFinding($finding, $report->baseline?->stateFor($finding->id));
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    private function renderFinding(Finding $finding, ?string $baselineState = null): string
    {
        $lines = [];

        $lines[] = sprintf(
            '[%s] %s%s (%s, confidence: %s)',
            strtoupper($finding->severity->value),
            $baselineState === null ? '' : '['.strtoupper($baselineState).'] ',
            $finding->title,
            $finding->domain->label(),
            $finding->confidence->label(),
        );
        $lines[] = '  Rule: '.($finding->ruleId === '' ? 'unmapped' : $finding->ruleId);

        if ($finding->symbol !== null) {
            $lines[] = '  Symbol: '.$finding->symbol;
        }

        foreach ($finding->evidence->all() as $evidence) {
            $lines[] = '  Evidence: '.$evidence->location();
        }

        $lines[] = '';
        $lines[] = '  '.$finding->summary;
        $lines[] = '';
        $lines[] = '  Recommendation: '.$finding->recommendation;
        $lines[] = '';

        return implode(PHP_EOL, $lines);
    }
}
