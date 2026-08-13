<?php

declare(strict_types=1);

namespace LaravelAuditor\Audit\Reports;

/**
 * Renders an AuditReport as machine-readable JSON.
 */
final class JsonReportRenderer
{
    /**
     * @return array<string, mixed>
     */
    public function renderArray(AuditReport $report): array
    {
        return $report->toArray();
    }

    public function render(AuditReport $report): string
    {
        return json_encode($this->renderArray($report), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
