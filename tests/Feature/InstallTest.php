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

it('installs standalone agent resources without wiring agents when none are selected', function () {
    auditorCleanupInstallArtifacts();

    $this->artisan('auditor:install', ['--no-interaction' => true])
        ->expectsOutputToContain('standalone')
        ->expectsOutputToContain('No agents selected')
        ->assertSuccessful();

    expect(file_exists(base_path('.ai/skills/laravel-audit/SKILL.md')))->toBeTrue();
    expect(file_exists(base_path('.ai/guidelines/core.md')))->toBeTrue();
    expect(file_exists(base_path('.ai/schema/finding.schema.json')))->toBeTrue();
    expect(file_exists(base_path('.ai/examples/findings.json')))->toBeTrue();
    expect(file_exists(base_path('AGENTS.md')))->toBeFalse();
    expect(file_exists(base_path('CLAUDE.md')))->toBeFalse();
    expect(file_exists(base_path('GEMINI.md')))->toBeFalse();
    expect(file_exists(base_path('opencode.json')))->toBeFalse();
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

    $this->artisan('auditor:install', ['--agents' => ['opencode'], '--no-interaction' => true])
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
    expect($config['mcp']['laravel-auditor']['command'])->toBe(['php', 'artisan', 'auditor:mcp', '-q']);

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

it('does not treat an .agents directory as OpenCode', function () {
    auditorCleanupInstallArtifacts();

    mkdir(base_path('.agents'), 0777, true);

    $this->artisan('auditor:install', ['--no-interaction' => true])
        ->expectsOutputToContain('No agents selected')
        ->assertSuccessful();

    expect(file_exists(base_path('opencode.json')))->toBeFalse();
    expect(file_exists(base_path('.agents/skills/laravel-audit/SKILL.md')))->toBeFalse();
});

it('does not treat a GitHub or VS Code directory as Copilot', function () {
    auditorCleanupInstallArtifacts();

    mkdir(base_path('.github/workflows'), 0777, true);
    mkdir(base_path('.vscode'), 0777, true);

    $this->artisan('auditor:install', ['--no-interaction' => true])
        ->expectsOutputToContain('No agents selected')
        ->assertSuccessful();

    expect(file_exists(base_path('.github/copilot-instructions.md')))->toBeFalse();
    expect(file_exists(base_path('.github/skills/laravel-audit/SKILL.md')))->toBeFalse();
    expect(file_exists(base_path('.vscode/mcp.json')))->toBeFalse();
});

it('detects Copilot from copilot-instructions.md', function () {
    auditorCleanupInstallArtifacts();

    mkdir(base_path('.github'), 0777, true);
    file_put_contents(base_path('.github/copilot-instructions.md'), "# Existing Copilot instructions\n");

    $this->artisan('auditor:install', ['--no-interaction' => true])
        ->assertSuccessful();

    expect(file_exists(base_path('.github/skills/laravel-audit/SKILL.md')))->toBeTrue();
    expect(file_exists(base_path('.vscode/mcp.json')))->toBeTrue();
    expect((string) file_get_contents(base_path('.github/copilot-instructions.md')))->toBe("# Existing Copilot instructions\n");
});

it('does not overwrite an existing laravel-auditor mcp entry without force', function () {
    auditorCleanupInstallArtifacts();

    file_put_contents(base_path('opencode.json'), json_encode([
        'mcp' => [
            'laravel-auditor' => ['type' => 'local', 'command' => ['custom']],
        ],
    ], JSON_THROW_ON_ERROR));

    $this->artisan('auditor:install', ['--agents' => ['opencode'], '--no-interaction' => true])
        ->assertSuccessful();

    $config = json_decode((string) file_get_contents(base_path('opencode.json')), true);

    expect($config['mcp']['laravel-auditor']['command'])->toBe(['custom']);
});

it('refreshes an existing laravel-auditor mcp entry when forced', function () {
    auditorCleanupInstallArtifacts();

    file_put_contents(base_path('opencode.json'), json_encode([
        'mcp' => [
            'laravel-auditor' => ['type' => 'local', 'command' => ['custom']],
        ],
    ], JSON_THROW_ON_ERROR));

    $this->artisan('auditor:install', ['--agents' => ['opencode'], '--force' => true, '--no-interaction' => true])
        ->assertSuccessful();

    $config = json_decode((string) file_get_contents(base_path('opencode.json')), true);

    expect($config['mcp']['laravel-auditor']['command'])->toBe(['php', 'artisan', 'auditor:mcp', '-q']);
});

it('does not duplicate an existing Codex mcp entry', function () {
    auditorCleanupInstallArtifacts();

    $path = base_path('.codex/config.toml');
    mkdir(dirname($path), 0777, true);
    file_put_contents($path, "[mcp_servers.laravel-auditor]\ncommand = \"php\"\nargs = [\"artisan\", \"auditor:mcp\"]\n");

    $this->artisan('auditor:install', ['--agents' => ['codex'], '--no-interaction' => true])
        ->assertSuccessful();

    expect(substr_count((string) file_get_contents($path), '[mcp_servers.laravel-auditor]'))->toBe(1);
    expect((string) file_get_contents($path))->not->toContain('-q');
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
