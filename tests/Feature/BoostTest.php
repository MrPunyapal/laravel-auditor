<?php

declare(strict_types=1);

use LaravelAuditor\Support\BoostDetector;

it('reports that Boost is absent in the package test application', function () {
    $detector = app(BoostDetector::class);

    expect($detector->isInstalled())->toBeFalse();
    expect($detector->version())->toBeNull();
    expect($detector->supportsThirdPartyResources())->toBeFalse();
    expect($detector::PACKAGE)->toBe('laravel/boost');
});

it('exposes Boost third-party guidelines and skills from the package', function () {
    expect(file_exists(__DIR__.'/../../resources/boost/guidelines/core.blade.php'))->toBeTrue();
    expect(file_exists(__DIR__.'/../../resources/boost/skills/laravel-audit/SKILL.md'))->toBeTrue();
    expect(file_exists(__DIR__.'/../../resources/boost/skills/laravel-auditor-development/SKILL.md'))->toBeTrue();
});

it('describes the standalone mechanism when Boost is absent', function () {
    $this->artisan('auditor:status')
        ->expectsOutputToContain('not installed')
        ->expectsOutputToContain('Standalone installer')
        ->assertSuccessful();
});

it('describes the Boost mechanism when Boost is present', function () {
    $detector = new class extends BoostDetector
    {
        public function isInstalled(): bool
        {
            return true;
        }

        public function version(): ?string
        {
            return '1.8.0';
        }

        public function supportsThirdPartyResources(): bool
        {
            return true;
        }
    };

    $this->app->instance(BoostDetector::class, $detector);

    $this->artisan('auditor:status')
        ->expectsOutputToContain('1.8.0')
        ->expectsOutputToContain('Third-party guidelines/skills')
        ->assertSuccessful();
});
