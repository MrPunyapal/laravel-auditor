<?php

declare(strict_types=1);

namespace LaravelAuditor\Audit\Reports;

use LaravelAuditor\Audit\Enums\FindingStatus;
use LaravelAuditor\Audit\Enums\Severity;
use LaravelAuditor\Audit\Findings\Finding;

/**
 * Emits GitHub Actions workflow commands without requiring a GitHub API token.
 */
final class GithubAnnotationRenderer
{
    public function render(AuditReport $report): string
    {
        $lines = [];

        if ($report->baseline !== null) {
            $summary = $report->baseline->summary();
            $lines[] = sprintf('::notice title=Laravel Auditor baseline::%d new, %d persistent, %d absent from current findings file; %d regressions. Historical status %s.', $summary['new'], $summary['persistent'], $summary['absent'], count($report->baseline->regressions), $report->baseline->statusHistoryAvailable ? 'available' : 'unknown');
        }

        foreach ($report->findings->sorted()->all() as $finding) {
            if ($finding->status !== FindingStatus::Open) {
                continue;
            }

            $lines[] = $this->annotation($finding, $report->baseline?->stateFor($finding->id));
        }

        return $lines === [] ? '' : implode(PHP_EOL, $lines).PHP_EOL;
    }

    private function annotation(Finding $finding, ?string $baselineState = null): string
    {
        $label = $baselineState === null ? '' : '['.strtoupper($baselineState).'] ';
        $properties = ['title='.$this->escapeProperty($label.$finding->ruleId.': '.$finding->title)];

        foreach ($finding->evidence->all() as $evidence) {
            if ($evidence->type !== 'file' || ! $this->safePath($evidence->reference)) {
                continue;
            }

            $properties[] = 'file='.$this->escapeProperty($evidence->reference);

            if ($evidence->line !== null && $evidence->line > 0) {
                $properties[] = 'line='.$evidence->line;

                if ($evidence->endLine !== null && $evidence->endLine >= $evidence->line) {
                    $properties[] = 'endLine='.$evidence->endLine;
                }
            }

            break;
        }

        $level = match ($finding->severity) {
            Severity::Critical, Severity::High => 'error',
            Severity::Medium => 'warning',
            Severity::Low, Severity::Info => 'notice',
        };

        $message = $finding->summary;

        if ($finding->recommendation !== '') {
            $message .= ' Suggested fix: '.$finding->recommendation;
        }

        return '::'.$level.' '.implode(',', $properties).'::'.$this->escapeData($message);
    }

    private function safePath(string $path): bool
    {
        $path = str_replace('\\', '/', $path);

        return $path !== ''
            && ! str_starts_with($path, '/')
            && preg_match('/^[A-Za-z]:/', $path) !== 1
            && ! in_array('..', explode('/', $path), true)
            && preg_match('/[\x00-\x1F\x7F]/', $path) !== 1;
    }

    private function escapeData(string $value): string
    {
        return str_replace(['%', "\r", "\n"], ['%25', '%0D', '%0A'], $value);
    }

    private function escapeProperty(string $value): string
    {
        return str_replace(['%', "\r", "\n", ':', ','], ['%25', '%0D', '%0A', '%3A', '%2C'], $value);
    }
}
