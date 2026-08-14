<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LaravelAuditor\Audit\Enums\AuditDomain;
use LaravelAuditor\Facades\LaravelAuditor;

it('lists rules in a table and accepts a domain filter', function () {
    $this->artisan('auditor:rules')
        ->expectsOutputToContain('AUD-SEC-001')
        ->expectsOutputToContain('security')
        ->assertSuccessful();

    $this->artisan('auditor:rules', ['--domain' => 'database'])
        ->expectsOutputToContain('AUD-DB-001')
        ->assertSuccessful();
});

it('rejects an unknown rule domain', function () {
    $this->artisan('auditor:rules', ['--domain' => 'bogus'])
        ->expectsOutputToContain('Unknown domain')
        ->assertFailed();
});

it('emits rules as JSON', function () {
    $exit = Artisan::call('auditor:rules', ['--json' => true]);

    expect($exit)->toBe(0);

    $payload = json_decode(Artisan::output(), true);

    expect($payload)->toBeArray()->not->toBeEmpty();
    expect($payload[0]['id'])->toBeString();
    expect($payload[0]['domain'])->toBeString();
    expect($payload[0]['severity'])->toBeString();
});

it('scopes the JSON rule list to a domain', function () {
    $exit = Artisan::call('auditor:rules', ['--json' => true, '--domain' => 'security']);

    expect($exit)->toBe(0);

    $payload = json_decode(Artisan::output(), true);

    expect($payload)->not->toBeEmpty();
    expect(array_unique(array_column($payload, 'domain')))->toBe(['security']);
});

it('generates a markdown report with project facts and an empty findings summary', function () {
    $this->artisan('auditor:report')
        ->expectsOutputToContain('# Laravel Auditor Report')
        ->assertSuccessful();
});

it('generates a JSON report', function () {
    $exit = Artisan::call('auditor:report', ['--format' => 'json']);

    expect($exit)->toBe(0);

    $payload = json_decode(Artisan::output(), true);

    expect($payload)->toHaveKeys(['meta', 'project', 'domains_run', 'summary', 'findings']);
    expect($payload['summary']['total_findings'])->toBe(0);
});

it('generates a text report', function () {
    $this->artisan('auditor:report', ['--format' => 'text'])
        ->expectsOutputToContain('Laravel Auditor Report')
        ->assertSuccessful();
});

it('writes a report to a file', function () {
    $path = sys_get_temp_dir().'/laravel-auditor-report-'.uniqid().'.md';

    $this->artisan('auditor:report', ['--output' => $path])
        ->expectsOutputToContain('Report written')
        ->assertSuccessful();

    expect(file_exists($path))->toBeTrue();
    expect((string) file_get_contents($path))->toContain('# Laravel Auditor Report');

    unlink($path);
});

it('rejects an unknown report format', function () {
    $this->artisan('auditor:report', ['--format' => 'html'])
        ->expectsOutputToContain('Unknown format')
        ->assertFailed();
});

it('rejects a missing findings file', function () {
    $this->artisan('auditor:report', ['--findings' => '/nonexistent/findings.json'])
        ->expectsOutputToContain('does not exist')
        ->assertFailed();
});

it('loads findings from a JSON file into the report', function () {
    $path = sys_get_temp_dir().'/laravel-auditor-findings-'.uniqid().'.json';

    file_put_contents($path, json_encode([
        'findings' => [
            [
                'id' => 'F-1',
                'rule_id' => 'AUD-SEC-001',
                'title' => 'Test',
                'domain' => 'security',
                'severity' => 'high',
                'confidence' => 'confirmed',
                'status' => 'open',
                'summary' => 'Summary',
                'why_it_matters' => 'Why',
            ],
        ],
    ], JSON_THROW_ON_ERROR));

    $exit = Artisan::call('auditor:report', ['--format' => 'json', '--findings' => $path]);

    expect($exit)->toBe(0);

    $payload = json_decode(Artisan::output(), true);

    expect($payload['summary']['total_findings'])->toBe(1);
    expect($payload['findings'][0]['id'])->toBe('F-1');
    expect($payload['key_risks'])->toHaveCount(1);

    unlink($path);
});

it('rejects invalid findings JSON', function () {
    $path = sys_get_temp_dir().'/laravel-auditor-bad-'.uniqid().'.json';
    file_put_contents($path, 'not json');

    $this->artisan('auditor:report', ['--findings' => $path])
        ->expectsOutputToContain('Invalid findings JSON')
        ->assertFailed();

    unlink($path);
});

it('lists context collectors and dumps JSON', function () {
    $this->artisan('auditor:context', ['--list' => true])
        ->expectsOutputToContain('project_info')
        ->assertSuccessful();

    $exit = Artisan::call('auditor:context', ['collector' => 'project_info']);

    expect($exit)->toBe(0);
    expect(json_decode(Artisan::output(), true))->toHaveKey('php_version');
});

it('writes context JSON to a file', function () {
    $path = sys_get_temp_dir().'/laravel-auditor-context-'.uniqid().'.json';

    $this->artisan('auditor:context', ['collector' => 'routes', '--output' => $path])
        ->expectsOutputToContain('Context written')
        ->assertSuccessful();

    expect(file_exists($path))->toBeTrue();
    expect(json_decode((string) file_get_contents($path), true))->toHaveKey('routes');

    unlink($path);
});

it('rejects an unknown context collector', function () {
    $this->artisan('auditor:context', ['collector' => 'nope'])
        ->expectsOutputToContain('Unknown context collector')
        ->assertFailed();
});

it('renders the packaged example report', function () {
    $exit = Artisan::call('auditor:report', ['--example' => true]);

    expect($exit)->toBe(0);
    expect(Artisan::output())
        ->toContain('AUD-SEC-001')
        ->toContain('Missing authorization boundary');
});

it('resolves the LaravelAuditor facade', function () {
    expect(LaravelAuditor::rules()->count())->toBeGreaterThanOrEqual(50);
    expect(LaravelAuditor::collect('project_info'))->toHaveKey('laravel_version');
});

it('publishes the configuration file', function () {
    $this->artisan('vendor:publish', ['--tag' => 'laravel-auditor-config', '--force' => true])
        ->assertSuccessful();

    expect(file_exists(config_path('laravel-auditor.php')))->toBeTrue();
});

it('installs resources in dry-run mode without writing files', function () {
    $this->artisan('auditor:install', ['--dry-run' => true])
        ->expectsOutputToContain('Dry run: no files were written.')
        ->assertSuccessful();
});

it('reports the status with domains and rule counts', function () {
    $this->artisan('auditor:status')
        ->expectsOutputToContain('Audit domains')
        ->expectsOutputToContain(AuditDomain::Security->value)
        ->expectsOutputToContain('Total rules')
        ->assertSuccessful();
});
