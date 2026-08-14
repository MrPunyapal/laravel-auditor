<?php

declare(strict_types=1);

use LaravelAuditor\Audit\Enums\AuditDomain;
use LaravelAuditor\Audit\Enums\Confidence;
use LaravelAuditor\Audit\Enums\Severity;
use LaravelAuditor\Audit\Evidence\Evidence;
use LaravelAuditor\Audit\Evidence\EvidenceCollection;
use LaravelAuditor\Audit\Findings\Finding;
use LaravelAuditor\Audit\Findings\FindingCollection;
use LaravelAuditor\Audit\Reports\AuditReport;
use LaravelAuditor\Audit\Reports\JsonReportRenderer;
use LaravelAuditor\Audit\Reports\MarkdownReportRenderer;
use LaravelAuditor\Audit\Reports\TextReportRenderer;

function auditorSampleReport(bool $withFinding = true): AuditReport
{
    $findings = $withFinding
        ? new FindingCollection(new Finding(
            id: 'F-1',
            ruleId: 'AUD-SEC-001',
            title: 'Missing authorization boundary',
            domain: AuditDomain::Security,
            severity: Severity::High,
            confidence: Confidence::Confirmed,
            summary: 'Posts can be deleted by any authenticated user.',
            whyItMatters: 'Users could delete content they do not own.',
            evidence: new EvidenceCollection(Evidence::file('app/Http/Controllers/PostController.php', 42, 48, 'destroy')),
            affectedResources: ['app/Http/Controllers/PostController.php'],
            symbol: 'App\\Http\\Controllers\\PostController@destroy',
            recommendation: 'Authorize the deletion.',
            remediation: 'Add a PostPolicy.',
            verificationNotes: 'Route middleware does not include can:delete,post.',
        ))
        : new FindingCollection;

    return new AuditReport(
        project: [
            'name' => 'Demo',
            'laravel_version' => '13.0.0',
            'php_version' => '8.4.0',
            'frontend' => ['none'],
        ],
        domainsRun: ['security', 'testing'],
        findings: $findings,
        meta: ['generated_at' => '2026-08-14 12:00:00'],
    );
}

it('serializes a report with summary counts and key risks', function () {
    $report = auditorSampleReport();

    expect($report->totalFindings())->toBe(1);
    expect($report->countsBySeverity()['high'])->toBe(1);
    expect($report->countsByDomain()['security'])->toBe(1);
    expect($report->keyRisks())->toHaveCount(1);
    expect($report->priorityTiers()['p0'])->toBe(['F-1']);
    expect($report->jsonSerialize()['summary']['priority_tiers']['p0'])->toBe(['F-1']);
    expect($report->jsonSerialize()['findings'][0]['rule_id'])->toBe('AUD-SEC-001');
});

it('renders a markdown report with findings and evidence', function () {
    $markdown = (new MarkdownReportRenderer)->render(auditorSampleReport());

    expect($markdown)
        ->toContain('# Laravel Auditor Report')
        ->toContain('**Generated:** 2026-08-14 12:00:00')
        ->toContain('Missing authorization boundary')
        ->toContain('app/Http/Controllers/PostController.php:42-48')
        ->toContain('Add a PostPolicy.')
        ->toContain('Security')
        ->toContain('Priority synthesis')
        ->toContain('P0');
});

it('renders an empty markdown report', function () {
    $markdown = (new MarkdownReportRenderer)->render(auditorSampleReport(false));

    expect($markdown)->toContain('No findings were produced for this audit.');
});

it('renders a JSON report', function () {
    $json = (new JsonReportRenderer)->render(auditorSampleReport());
    $payload = json_decode($json, true);

    expect($payload['summary']['total_findings'])->toBe(1);
    expect($payload['key_risks'][0]['id'])->toBe('F-1');
    expect((new JsonReportRenderer)->renderArray(auditorSampleReport())['meta']['generated_at'])->toBe('2026-08-14 12:00:00');
});

it('renders a text report', function () {
    $text = (new TextReportRenderer)->render(auditorSampleReport());

    expect($text)
        ->toContain('Laravel Auditor Report')
        ->toContain('Project: Demo')
        ->toContain('[HIGH] Missing authorization boundary')
        ->toContain('Evidence: app/Http/Controllers/PostController.php:42');
});

it('renders empty project and domain sections', function () {
    $report = new AuditReport(
        project: [],
        domainsRun: [],
        findings: new FindingCollection,
    );

    $markdown = (new MarkdownReportRenderer)->render($report);

    expect($markdown)
        ->toContain('No project facts were collected.')
        ->toContain('No domains were selected.');
});
