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
