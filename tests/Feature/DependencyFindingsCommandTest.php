<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LaravelAuditor\Audit\Findings\ComposerAbandonedFindings;
use LaravelAuditor\Audit\Findings\ComposerAdvisoryFindings;

it('fails instead of exporting a clean report when Composer audit is disabled', function () {
    config(['laravel-auditor.context.composer_audit' => false]);

    $this->artisan('auditor:dependency-findings')
        ->expectsOutputToContain('Composer audit unavailable')
        ->assertFailed();
});

it('registers the dependency findings export command', function () {
    expect(Artisan::all())->toHaveKey('auditor:dependency-findings');
});

it('accepts the abandoned-package option without bypassing disabled audits', function () {
    config(['laravel-auditor.context.composer_audit' => false]);

    $this->artisan('auditor:dependency-findings', ['--include-abandoned' => true])
        ->expectsOutputToContain('Composer audit unavailable')
        ->assertFailed();
});

it('keeps dependency findings compatible with report rendering and CI thresholds', function () {
    config(['laravel-auditor.context.composer_audit' => false]);
    $findings = array_merge(
        ComposerAdvisoryFindings::fromAudit(['advisories' => [[
            'package' => 'vendor/vulnerable',
            'advisory_id' => 'GHSA-example',
            'severity' => 'high',
        ]]]),
        ComposerAbandonedFindings::fromAudit(['abandoned' => ['vendor/old' => null]]),
    );
    $path = tempnam(sys_get_temp_dir(), 'auditor-dependencies-');

    try {
        file_put_contents($path, json_encode(['findings' => $findings], JSON_THROW_ON_ERROR));

        $this->artisan('auditor:report', ['--findings' => $path, '--format' => 'json'])
            ->expectsOutputToContain('AUD-DEP-001')
            ->assertSuccessful();
        $this->artisan('auditor:ci', ['--findings' => $path, '--fail-on' => 'high'])
            ->assertFailed();
        $this->artisan('auditor:ci', ['--findings' => $path, '--fail-on' => 'critical'])
            ->assertSuccessful();
    } finally {
        unlink($path);
    }
});
