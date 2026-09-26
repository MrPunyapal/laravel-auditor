<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LaravelAuditor\Audit\Enums\AuditDomain;
use LaravelAuditor\Audit\Enums\Confidence;
use LaravelAuditor\Audit\Enums\Severity;
use LaravelAuditor\Audit\Evidence\Evidence;
use LaravelAuditor\Audit\Evidence\EvidenceCollection;
use LaravelAuditor\Audit\Findings\Finding;
use LaravelAuditor\Audit\Findings\FindingCollection;
use LaravelAuditor\Audit\Findings\FindingLoader;
use LaravelAuditor\Audit\Reports\AuditReport;
use LaravelAuditor\Audit\Reports\TextReportRenderer;
use LaravelAuditor\Context\ContextRegistry;
use LaravelAuditor\MCP\Boost\BoostMcpRegistrar;
use LaravelAuditor\MCP\McpServer;
use LaravelAuditor\MCP\McpToolRegistry;
use LaravelAuditor\Support\BoostDetector;

it('honors the configured report format when --format is omitted', function () {
    config()->set('laravel-auditor.report.format', 'json');

    $exit = Artisan::call('auditor:report');

    expect($exit)->toBe(0);
    expect(json_decode(Artisan::output(), true))->toHaveKeys(['meta', 'project', 'summary']);
});

it('renders evidence end-lines in text reports', function () {
    $finding = new Finding(
        id: 'F-1',
        ruleId: 'AUD-ARC-006',
        title: 'Big method',
        domain: AuditDomain::Architecture,
        severity: Severity::Medium,
        confidence: Confidence::High,
        summary: 'Summary',
        whyItMatters: 'Why',
        evidence: new EvidenceCollection(Evidence::file('app/Foo.php', 42, 44)),
    );

    $report = new AuditReport(
        project: ['name' => 'Test'],
        domainsRun: ['architecture'],
        findings: new FindingCollection($finding),
    );

    expect((new TextReportRenderer)->render($report))->toContain('app/Foo.php:42-44');
});

it('wraps invalid findings with the file path', function () {
    $path = sys_get_temp_dir().'/laravel-auditor-findings-'.uniqid().'.json';
    file_put_contents($path, json_encode([['id' => 'F-1', 'domain' => 'bogus']]));

    try {
        app(FindingLoader::class)->load($path);
        $this->fail('Expected a RuntimeException.');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toContain($path);
    } finally {
        unlink($path);
    }
});

it('returns the unfiltered payload for empty filter arguments', function () {
    $context = app(ContextRegistry::class);

    foreach (['routes', 'models'] as $name) {
        $collector = $context->get($name);

        expect($collector->collectFiltered([]))->toBe($collector->collect());
    }
});

it('keeps the Boost tool map in sync with the context registry', function () {
    expect(array_keys(BoostMcpRegistrar::collectorToolMap()))
        ->toEqualCanonicalizing(app(ContextRegistry::class)->names());
    expect((new BoostMcpRegistrar(app(BoostDetector::class)))->toolClasses())
        ->toHaveCount(count(app(ContextRegistry::class)->names()));
});

it('emits a parse error for malformed MCP frames', function () {
    $input = fopen('php://temp', 'r+');
    $output = fopen('php://temp', 'r+');

    fwrite($input, "not-json\n");
    rewind($input);

    (new McpServer(app(McpToolRegistry::class), $input, $output))->run();

    rewind($output);

    $response = json_decode((string) stream_get_contents($output), true);

    expect($response['error']['code'])->toBe(-32700);
    expect(array_key_exists('id', $response))->toBeTrue();
});

it('rejects conflicting findings and example options', function () {
    $this->artisan('auditor:report', ['--findings' => 'x.json', '--example' => true])
        ->expectsOutputToContain('either --findings or --example')
        ->assertFailed();
});

it('keeps the dependency version when a detail is also given', function () {
    $evidence = Evidence::dependency('pkg/name', '1.0', 'reason');

    expect($evidence->detail)->toBe('reason');
    expect($evidence->metadata['version'])->toBe('1.0');
    expect(Evidence::dependency('pkg/name', '1.0')->detail)->toBe('1.0');
});
