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

it('rejects absolute, traversal and control-character annotation paths', function (string $path) {
    $finding = Finding::fromArray([
        ...json_decode((string) file_get_contents(__DIR__.'/../../resources/auditor/examples/findings.json'), true)['findings'][0],
        'evidence' => [['type' => 'file', 'reference' => $path, 'line' => 1]],
    ]);

    expect((new GithubAnnotationRenderer)->render(new AuditReport([], [], new FindingCollection($finding))))
        ->not->toContain('file=');
})->with([
    '/tmp/source.php',
    'C:\\source.php',
    '\\\\server\\source.php',
    'app/../source.php',
    "app/source\0.php",
    "app/source\t.php",
    "app/source\r\n::error::injected.php",
]);

it('escapes workflow command delimiters and maps all severities', function (Severity $severity, string $level) {
    $finding = Finding::fromArray([
        ...json_decode((string) file_get_contents(__DIR__.'/../../resources/auditor/examples/findings.json'), true)['findings'][0],
        'severity' => $severity->value,
        'title' => "Title%,:\r\n::error::injected",
        'summary' => "Message%0A\r\n::error::injected",
        'recommendation' => '',
        'evidence' => [['type' => 'file', 'reference' => 'app/file%,:.php', 'line' => -1]],
    ]);
    $result = (new GithubAnnotationRenderer)->render(new AuditReport([], [], new FindingCollection($finding)));

    expect($result)->toStartWith('::'.$level.' ')
        ->toContain('Title%25%2C%3A%0D%0A%3A%3Aerror%3A%3Ainjected')
        ->toContain('file=app/file%25%2C%3A.php::Message%250A%0D%0A::error::injected')
        ->not->toContain(',line=')
        ->and(substr_count(trim($result), "\n"))->toBe(0);
})->with([
    [Severity::Critical, 'error'],
    [Severity::High, 'error'],
    [Severity::Medium, 'warning'],
    [Severity::Low, 'notice'],
    [Severity::Info, 'notice'],
]);

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

it('preserves literal console markup in annotation messages from both commands', function () {
    $path = sys_get_temp_dir().'/laravel-auditor-github-markup-'.uniqid().'.json';
    $findings = json_decode((string) file_get_contents(__DIR__.'/../../resources/auditor/examples/findings.json'), true);
    $findings['findings'][0]['summary'] = '<info>Untrusted HTML</info> and <error>literal markup</error>';

    try {
        file_put_contents($path, json_encode($findings, JSON_THROW_ON_ERROR));

        foreach (['auditor:report', 'auditor:ci'] as $command) {
            Artisan::call($command, ['--findings' => $path, '--format' => 'github']);

            expect(Artisan::output())->toContain('<info>Untrusted HTML</info> and <error>literal markup</error>');
        }
    } finally {
        if (file_exists($path)) {
            unlink($path);
        }
    }
});
