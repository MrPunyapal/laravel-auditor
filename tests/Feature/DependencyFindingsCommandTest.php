<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

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
