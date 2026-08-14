<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use LaravelAuditor\Support\BoostDetector;

function auditorCleanupInstallArtifacts(): void
{
    $files = new Filesystem;

    $files->deleteDirectory(base_path('.ai'));

    foreach ([
        'AGENTS.md',
        'CLAUDE.md',
        'GEMINI.md',
        '.cursor/rules/laravel-auditor.mdc',
        '.github/copilot-instructions.md',
    ] as $adapter) {
        $path = base_path($adapter);

        if (! $files->exists($path)) {
            continue;
        }

        $contents = $files->get($path);

        if (str_contains($contents, '<!-- laravel-auditor -->')) {
            $files->delete($path);
        }
    }
}

afterEach(function (): void {
    auditorCleanupInstallArtifacts();
});

it('installs standalone agent resources and thin adapters', function () {
    auditorCleanupInstallArtifacts();

    $this->artisan('auditor:install')
        ->expectsOutputToContain('standalone')
        ->assertSuccessful();

    expect(file_exists(base_path('.ai/skills/laravel-audit/SKILL.md')))->toBeTrue();
    expect(file_exists(base_path('.ai/guidelines/core.md')))->toBeTrue();
    expect(file_exists(base_path('.ai/schema/finding.schema.json')))->toBeTrue();
    expect(file_exists(base_path('.ai/examples/findings.json')))->toBeTrue();
    expect((string) file_get_contents(base_path('AGENTS.md')))->toContain('<!-- laravel-auditor -->');
    expect((string) file_get_contents(base_path('CLAUDE.md')))->toContain('laravel-audit');
    expect((string) file_get_contents(base_path('GEMINI.md')))->toContain('laravel-audit');
    expect((string) file_get_contents(base_path('.cursor/rules/laravel-auditor.mdc')))->toContain('laravel-audit');
});

it('is idempotent and does not overwrite existing standalone resources', function () {
    auditorCleanupInstallArtifacts();

    $this->artisan('auditor:install')->assertSuccessful();

    $skill = base_path('.ai/skills/laravel-audit/SKILL.md');
    file_put_contents($skill, "user-edited\n");

    $this->artisan('auditor:install')
        ->expectsOutputToContain('Up to date')
        ->assertSuccessful();

    expect((string) file_get_contents($skill))->toBe("user-edited\n");
});

it('refreshes standalone resources when forced', function () {
    auditorCleanupInstallArtifacts();

    $this->artisan('auditor:install')->assertSuccessful();

    $skill = base_path('.ai/skills/laravel-audit/SKILL.md');
    file_put_contents($skill, "user-edited\n");

    $this->artisan('auditor:install', ['--force' => true])->assertSuccessful();

    expect((string) file_get_contents($skill))->toContain('Laravel Audit');
});

it('does not write files during a dry run', function () {
    auditorCleanupInstallArtifacts();

    $this->artisan('auditor:install', ['--dry-run' => true])
        ->expectsOutputToContain('Dry run: no files were written.')
        ->assertSuccessful();

    expect(is_dir(base_path('.ai')))->toBeFalse();
    expect(file_exists(base_path('AGENTS.md')))->toBeFalse();
});

it('leaves a user-owned adapter file unchanged without force', function () {
    auditorCleanupInstallArtifacts();

    file_put_contents(base_path('AGENTS.md'), "# Existing project instructions\n");

    $this->artisan('auditor:install')
        ->expectsOutputToContain('left unchanged')
        ->assertSuccessful();

    expect((string) file_get_contents(base_path('AGENTS.md')))->toBe("# Existing project instructions\n");

    unlink(base_path('AGENTS.md'));
});

it('does not copy standalone resources when Boost is installed', function () {
    auditorCleanupInstallArtifacts();

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
    };

    $this->app->instance(BoostDetector::class, $detector);

    $this->artisan('auditor:install')
        ->expectsOutputToContain('Laravel Boost detected')
        ->expectsOutputToContain('boost:install')
        ->assertSuccessful();

    expect(is_dir(base_path('.ai')))->toBeFalse();
    expect(file_exists(base_path('AGENTS.md')))->toBeFalse();
});
