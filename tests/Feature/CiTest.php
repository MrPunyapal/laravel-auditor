<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LaravelAuditor\Audit\Enums\AuditDomain;
use LaravelAuditor\Audit\Enums\Confidence;
use LaravelAuditor\Audit\Enums\Severity;
use LaravelAuditor\Audit\Evidence\EvidenceCollection;
use LaravelAuditor\Audit\Findings\Finding;
use LaravelAuditor\Audit\Findings\FindingCollection;
use LaravelAuditor\Audit\Findings\FindingLoader;
use LaravelAuditor\Audit\Reports\AuditReport;
use LaravelAuditor\Audit\Reports\SarifReportRenderer;

it('fails CI when an open high finding meets the threshold', function () {
    $path = sys_get_temp_dir().'/laravel-auditor-ci-'.uniqid().'.json';
    copy(__DIR__.'/../../resources/auditor/examples/findings.json', $path);

    $this->artisan('auditor:ci', ['--findings' => $path, '--fail-on' => 'high'])
        ->expectsOutputToContain('CI failed')
        ->assertFailed();

    unlink($path);
});

it('passes CI when findings are below the threshold', function () {
    $path = sys_get_temp_dir().'/laravel-auditor-ci-'.uniqid().'.json';
    copy(__DIR__.'/../../resources/auditor/examples/findings.json', $path);

    $this->artisan('auditor:ci', ['--findings' => $path, '--fail-on' => 'critical'])
        ->expectsOutputToContain('CI passed')
        ->assertSuccessful();

    unlink($path);
});

it('requires a findings file', function () {
    $this->artisan('auditor:ci')
        ->expectsOutputToContain('--findings=')
        ->assertFailed();
});

it('rejects an unknown CI severity', function () {
    $path = sys_get_temp_dir().'/laravel-auditor-ci-'.uniqid().'.json';
    copy(__DIR__.'/../../resources/auditor/examples/findings.json', $path);

    $this->artisan('auditor:ci', ['--findings' => $path, '--fail-on' => 'urgent'])
        ->expectsOutputToContain('Unknown severity')
        ->assertFailed();

    unlink($path);
});

it('writes a SARIF report', function () {
    $exit = Artisan::call('auditor:report', ['--example' => true, '--format' => 'sarif']);

    expect($exit)->toBe(0);

    $payload = json_decode(Artisan::output(), true);

    expect($payload['version'])->toBe('2.1.0');
    expect($payload['runs'][0]['results'][0]['ruleId'])->toBe('AUD-SEC-001');
});

it('lists only applicable rules when asked', function () {
    $exit = Artisan::call('auditor:rules', ['--applicable' => true, '--json' => true]);

    expect($exit)->toBe(0);

    $payload = json_decode(Artisan::output(), true);

    expect($payload)->toBeArray();

    $ids = array_column($payload, 'id');

    expect($ids)->toContain('AUD-SEC-001');
    expect($ids)->not->toContain('AUD-LW-001');
    expect($ids)->toContain('AUD-PEST-001');
});

it('loads findings through FindingLoader', function () {
    $collection = app(FindingLoader::class)->load(__DIR__.'/../../resources/auditor/examples/findings.json');

    expect($collection)->toHaveCount(1);
    expect($collection[0]->ruleId)->toBe('AUD-SEC-001');
});

it('renders SARIF from a report model', function () {
    $report = new AuditReport(
        project: ['name' => 'Demo'],
        domainsRun: ['security'],
        findings: new FindingCollection(new Finding(
            id: 'F-1',
            ruleId: 'AUD-SEC-001',
            title: 'Test',
            domain: AuditDomain::Security,
            severity: Severity::High,
            confidence: Confidence::High,
            summary: 'Summary',
            whyItMatters: 'Why',
            evidence: new EvidenceCollection,
        )),
    );

    $payload = (new SarifReportRenderer)->renderArray($report);

    expect($payload['runs'][0]['results'][0]['level'])->toBe('error');
});
