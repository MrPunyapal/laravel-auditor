<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LaravelAuditor\Audit\Enums\AuditDomain;
use LaravelAuditor\Audit\Enums\Confidence;
use LaravelAuditor\Audit\Enums\FindingStatus;
use LaravelAuditor\Audit\Enums\Severity;
use LaravelAuditor\Audit\Evidence\Evidence;
use LaravelAuditor\Audit\Evidence\EvidenceCollection;
use LaravelAuditor\Audit\Findings\Finding;
use LaravelAuditor\Audit\Findings\FindingCollection;
use LaravelAuditor\Audit\Reports\AuditReport;
use LaravelAuditor\Audit\Reports\GithubAnnotationRenderer;

it('renders escaped workflow annotations for open findings only', function () {
    $open = new Finding(
        id: 'F-1',
        ruleId: 'AUD-SEC-001',
        title: 'Auth, input',
        domain: AuditDomain::Security,
        severity: Severity::High,
        confidence: Confidence::Confirmed,
        summary: "One% issue\nTwo lines",
        whyItMatters: 'Why',
        evidence: new EvidenceCollection(Evidence::file('app/Http/PostController.php', 42, 44)),
        recommendation: 'Authorize updates.',
    );

    $resolved = new Finding(
        id: 'F-2',
        ruleId: 'AUD-SEC-001',
        title: 'Old',
        domain: AuditDomain::Security,
        severity: Severity::Low,
        confidence: Confidence::High,
        summary: 'Resolved',
        whyItMatters: 'Why',
        evidence: new EvidenceCollection,
        status: FindingStatus::Fixed,
    );

    $report = new AuditReport([], [], new FindingCollection($open, $resolved));
    $result = (new GithubAnnotationRenderer)->render($report);

    expect($result)
        ->toContain('::error title=AUD-SEC-001%3A Auth%2C input,file=app/Http/PostController.php,line=42,endLine=44::')
        ->toContain('One%25 issue%0ATwo lines Suggested fix: Authorize updates.')
        ->not->toContain('Resolved');
});

it('omits unsafe file locations instead of writing command properties', function () {
    $finding = new Finding(
        id: 'F-3',
        ruleId: 'AUD-SEC-001',
        title: 'Risk',
        domain: AuditDomain::Security,
        severity: Severity::Medium,
        confidence: Confidence::High,
        summary: 'Problem',
        whyItMatters: 'Why',
        evidence: new EvidenceCollection(Evidence::file('../outside.php', 3)),
    );

    $report = new AuditReport([], [], new FindingCollection($finding));

    expect((new GithubAnnotationRenderer)->render($report))
        ->toContain('::warning title=AUD-SEC-001%3A Risk::Problem')
        ->not->toContain('file=');
});

it('supports GitHub annotation output from existing report and CI commands', function () {
    expect(Artisan::call('auditor:report', ['--example' => true, '--format' => 'github']))->toBe(0);

    expect(Artisan::output())->toContain('::error ');

    $path = sys_get_temp_dir().'/laravel-auditor-github-'.uniqid().'.json';

    try {
        copy(__DIR__.'/../../resources/auditor/examples/findings.json', $path);

        expect(Artisan::call('auditor:ci', [
            '--findings' => $path,
            '--format' => 'github',
            '--fail-on' => 'critical',
        ]))->toBe(0);

        expect(Artisan::output())->toContain('::error ');
    } finally {
        if (file_exists($path)) {
            unlink($path);
        }
    }
});
