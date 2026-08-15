<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use LaravelAuditor\Support\Agents\AgentRegistry;
use LaravelAuditor\Support\BoostDetector;

function auditorCleanupInstallArtifacts(): void
{
    $files = new Filesystem;

    $files->deleteDirectory(base_path('.ai'));

    foreach ([
        'AGENTS.md',
        'CLAUDE.md',
        'GEMINI.md',
        'opencode.json',
        'opencode.jsonc',
        '.mcp.json',
    ] as $adapter) {
        $path = base_path($adapter);

        if ($files->isDirectory($path)) {
            $files->deleteDirectory($path);
        } elseif ($files->exists($path)) {
            $files->delete($path);
        }
    }

    foreach ([
        '.agents',
        '.claude',
        '.cursor',
        '.github',
        '.gemini',
        '.codex',
        '.junie',
        '.zed',
        '.vscode',
    ] as $directory) {
        $files->deleteDirectory(base_path($directory));
    }
}

afterEach(function (): void {
    auditorCleanupInstallArtifacts();
});

it('installs standalone agent resources and thin adapters', function () {
    auditorCleanupInstallArtifacts();

    $this->artisan('auditor:install', ['--no-interaction' => true])
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

    $this->artisan('auditor:install', ['--no-interaction' => true])->assertSuccessful();

    $skill = base_path('.ai/skills/laravel-audit/SKILL.md');
    file_put_contents($skill, "user-edited\n");

    $this->artisan('auditor:install', ['--no-interaction' => true])
        ->expectsOutputToContain('Up to date')
        ->assertSuccessful();

    expect((string) file_get_contents($skill))->toBe("user-edited\n");
});

it('refreshes standalone resources when forced', function () {
    auditorCleanupInstallArtifacts();

    $this->artisan('auditor:install', ['--no-interaction' => true])->assertSuccessful();

    $skill = base_path('.ai/skills/laravel-audit/SKILL.md');
    file_put_contents($skill, "user-edited\n");

    $this->artisan('auditor:install', ['--force' => true, '--no-interaction' => true])->assertSuccessful();

    expect((string) file_get_contents($skill))->toContain('Laravel Audit');
});

it('does not write files during a dry run', function () {
    auditorCleanupInstallArtifacts();

    $this->artisan('auditor:install', ['--dry-run' => true, '--no-interaction' => true])
        ->expectsOutputToContain('Dry run: no files were written.')
        ->assertSuccessful();

    expect(is_dir(base_path('.ai')))->toBeFalse();
    expect(file_exists(base_path('AGENTS.md')))->toBeFalse();
    expect(file_exists(base_path('opencode.json')))->toBeFalse();
});

it('leaves a user-owned adapter file unchanged without force', function () {
    auditorCleanupInstallArtifacts();

    file_put_contents(base_path('AGENTS.md'), "# Existing project instructions\n");

    $this->artisan('auditor:install', ['--no-interaction' => true])
        ->expectsOutputToContain('left unchanged')
        ->assertSuccessful();

    expect((string) file_get_contents(base_path('AGENTS.md')))->toBe("# Existing project instructions\n");

    unlink(base_path('AGENTS.md'));
});

it('wires skills, adapters, and mcp for a single selected agent', function () {
    auditorCleanupInstallArtifacts();

    $this->artisan('auditor:install', ['--agents' => ['opencode'], '--no-interaction' => true])
        ->assertSuccessful();

    expect(file_exists(base_path('.agents/skills/laravel-audit/SKILL.md')))->toBeTrue();
    expect((string) file_get_contents(base_path('AGENTS.md')))->toContain('<!-- laravel-auditor -->');

    $config = json_decode((string) file_get_contents(base_path('opencode.json')), true);
    expect($config['mcp']['laravel-auditor']['command'])->toBe(['php', 'artisan', 'auditor:mcp']);

    expect(file_exists(base_path('CLAUDE.md')))->toBeFalse();
    expect(file_exists(base_path('GEMINI.md')))->toBeFalse();
});

it('merges mcp config without clobbering an existing server', function () {
    auditorCleanupInstallArtifacts();

    file_put_contents(base_path('opencode.json'), json_encode([
        '$schema' => 'https://opencode.ai/config.json',
        'mcp' => [
            'laravel-boost' => ['type' => 'local', 'command' => ['php', 'artisan', 'boost:mcp']],
        ],
    ]));

    $this->artisan('auditor:install', ['--agents' => ['opencode'], '--no-interaction' => true])
        ->assertSuccessful();

    $config = json_decode((string) file_get_contents(base_path('opencode.json')), true);

    expect($config['mcp'])->toHaveKeys(['laravel-boost', 'laravel-auditor']);
});

it('uses project detection as the non-interactive default', function () {
    auditorCleanupInstallArtifacts();

    mkdir(base_path('.claude'));

    $this->artisan('auditor:install', ['--no-interaction' => true])
        ->assertSuccessful();

    expect(file_exists(base_path('.claude/skills/laravel-audit/SKILL.md')))->toBeTrue();
    expect(file_exists(base_path('CLAUDE.md')))->toBeTrue();
    expect(file_exists(base_path('opencode.json')))->toBeFalse();
});

it('uses the configured agents list as a non-interactive fallback', function () {
    auditorCleanupInstallArtifacts();

    config()->set('laravel-auditor.agents', ['cursor']);

    $this->artisan('auditor:install', ['--no-interaction' => true])
        ->assertSuccessful();

    expect(file_exists(base_path('.cursor/skills/laravel-audit/SKILL.md')))->toBeTrue();
    expect(file_exists(base_path('opencode.json')))->toBeFalse();
    expect(file_exists(base_path('CLAUDE.md')))->toBeFalse();
});

it('prompts for agents when interactive', function () {
    auditorCleanupInstallArtifacts();

    $options = collect(AgentRegistry::all())
        ->mapWithKeys(fn ($agent): array => [$agent->name => $agent->displayName])
        ->all();

    $this->artisan('auditor:install')
        ->expectsChoice('Which AI agents would you like to configure?', ['claude_code'], $options)
        ->assertSuccessful();

    expect(file_exists(base_path('.claude/skills/laravel-audit/SKILL.md')))->toBeTrue();
    expect(file_exists(base_path('CLAUDE.md')))->toBeTrue();
    expect(file_exists(base_path('.mcp.json')))->toBeTrue();
    expect(file_exists(base_path('opencode.json')))->toBeFalse();
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
