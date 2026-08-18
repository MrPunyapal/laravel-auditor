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
    expect(file_exists(__DIR__.'/../../resources/boost/guidelines/findings.blade.php'))->toBeTrue();
    expect(file_exists(__DIR__.'/../../resources/boost/guidelines/dsa.blade.php'))->toBeTrue();
    expect(file_exists(__DIR__.'/../../resources/boost/skills/laravel-audit/SKILL.md'))->toBeTrue();
    expect(file_exists(__DIR__.'/../../resources/boost/skills/laravel-auditor-development/SKILL.md'))->toBeTrue();
    expect(file_exists(__DIR__.'/../../resources/boost/skills/laravel-audit-security/SKILL.md'))->toBeTrue();
    expect(file_exists(__DIR__.'/../../resources/boost/skills/laravel-audit-testing/SKILL.md'))->toBeTrue();
    expect(file_exists(__DIR__.'/../../resources/boost/skills/laravel-audit-dsa/SKILL.md'))->toBeTrue();
    expect(file_exists(__DIR__.'/../../resources/auditor/skills/laravel-auditor-development/SKILL.md'))->toBeTrue();
    expect(file_exists(__DIR__.'/../../resources/auditor/schema/finding.schema.json'))->toBeTrue();
    expect(file_exists(__DIR__.'/../../resources/auditor/examples/findings.json'))->toBeTrue();
});

it('keeps auditor and Boost audit skills identical', function () {
    $skills = [
        'laravel-audit',
        'laravel-audit-security',
        'laravel-audit-performance',
        'laravel-audit-architecture',
        'laravel-audit-database',
        'laravel-audit-testing',
        'laravel-audit-conventions',
        'laravel-audit-dsa',
        'laravel-auditor-development',
    ];

    foreach ($skills as $skill) {
        $auditor = dirname(__DIR__, 2)."/resources/auditor/skills/{$skill}/SKILL.md";
        $boost = dirname(__DIR__, 2)."/resources/boost/skills/{$skill}/SKILL.md";

        expect(file_get_contents($auditor))->toBe(file_get_contents($boost), $skill);
    }
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
