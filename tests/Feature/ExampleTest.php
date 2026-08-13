<?php

declare(strict_types=1);

use LaravelAuditor\Audit\Enums\AuditDomain;
use LaravelAuditor\Audit\Enums\Confidence;
use LaravelAuditor\Audit\Enums\FindingStatus;
use LaravelAuditor\Audit\Enums\Severity;
use LaravelAuditor\LaravelAuditor;

it('resolves the singleton', function () {
    expect(app(LaravelAuditor::class))->toBeInstanceOf(LaravelAuditor::class);
});

it('returns the same instance from the container', function () {
    expect(app(LaravelAuditor::class))->toBe(app(LaravelAuditor::class));
});

it('merges the package config', function () {
    expect(config('laravel-auditor.domains'))->toBeArray()->toHaveCount(6);
});

it('registers the status command', function () {
    $this->artisan('auditor:status')->assertSuccessful();
});

it('registers the rules command', function () {
    $this->artisan('auditor:rules')
        ->expectsOutputToContain('AUD-SEC-001')
        ->assertSuccessful();
});

it('registers the install command', function () {
    $this->artisan('auditor:install', ['--dry-run' => true])->assertSuccessful();
});

it('exposes the six core audit domains', function () {
    $domains = AuditDomain::core();

    expect($domains)->toHaveCount(6);
});

it('wires severity and confidence levels', function () {
    expect(Severity::cases())->toHaveCount(5);
    expect(Confidence::cases())->toHaveCount(4);
    expect(FindingStatus::cases())->toHaveCount(4);
});
