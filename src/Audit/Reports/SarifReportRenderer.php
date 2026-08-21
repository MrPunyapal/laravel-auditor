<?php

declare(strict_types=1);

namespace LaravelAuditor\Audit\Reports;

use LaravelAuditor\Audit\Enums\Severity;
use LaravelAuditor\Audit\Findings\Finding;
use LaravelAuditor\Support\PackageVersion;

/**
 * Renders an AuditReport as SARIF 2.1.0 for CI annotations.
 */
final class SarifReportRenderer
{
    public function render(AuditReport $report): string
    {
        return json_encode($this->renderArray($report), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    public function renderArray(AuditReport $report): array
    {
        $results = [];

        foreach ($report->findings->sorted()->all() as $finding) {
            $results[] = $this->result($finding);
        }

        return [
            'version' => '2.1.0',
            '$schema' => 'https://json.schemastore.org/sarif-2.1.0.json',
            'runs' => [[
                'tool' => [
                    'driver' => [
                        'name' => 'laravel-auditor',
                        'informationUri' => 'https://github.com/mrpunyapal/laravel-auditor',
                        'version' => PackageVersion::current(),
                    ],
                ],
                'results' => $results,
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function result(Finding $finding): array
    {
        $locations = [];

        foreach ($finding->evidence->all() as $evidence) {
            if ($evidence->type !== 'file') {
                continue;
            }

            $locations[] = [
                'physicalLocation' => [
                    'artifactLocation' => ['uri' => $evidence->reference],
                    'region' => array_filter([
                        'startLine' => $evidence->line,
                        'endLine' => $evidence->endLine,
                    ], static fn (mixed $value): bool => $value !== null),
                ],
            ];
        }

        return array_filter([
            'ruleId' => $finding->ruleId === '' ? null : $finding->ruleId,
            'level' => $this->level($finding->severity),
            'message' => ['text' => $finding->summary],
            'locations' => $locations === [] ? null : $locations,
            'properties' => [
                'id' => $finding->id,
                'title' => $finding->title,
                'domain' => $finding->domain->value,
                'confidence' => $finding->confidence->value,
                'recommendation' => $finding->recommendation,
            ],
        ], static fn (mixed $value): bool => $value !== null);
    }

    private function level(Severity $severity): string
    {
        return match ($severity) {
            Severity::Critical, Severity::High => 'error',
            Severity::Medium => 'warning',
            Severity::Low, Severity::Info => 'note',
        };
    }
}
