<?php

declare(strict_types=1);

namespace LaravelAuditor\Audit\Reports;

/**
 * Renders an AuditReport in the requested format.
 *
 * Single home for the renderer map so report and CI commands cannot diverge.
 */
final class ReportRendererFactory
{
    public static function render(AuditReport $report, string $format): string
    {
        return match ($format) {
            'json' => (new JsonReportRenderer)->render($report),
            'text' => (new TextReportRenderer)->render($report),
            'sarif' => (new SarifReportRenderer)->render($report),
            default => (new MarkdownReportRenderer)->render($report),
        };
    }
}
