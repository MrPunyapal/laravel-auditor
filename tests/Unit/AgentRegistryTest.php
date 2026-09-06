<?php

declare(strict_types=1);

use LaravelAuditor\Support\Agents\Agent;
use LaravelAuditor\Support\Agents\AgentRegistry;

it('exposes eight built-in installer agents', function () {
    expect(AgentRegistry::builtIn())->toHaveCount(8);
    expect(AgentRegistry::all())->toHaveKeys([
        'opencode',
        'claude_code',
        'cursor',
        'copilot',
        'gemini',
        'codex',
        'junie',
        'zed',
    ]);
});

it('merges valid custom agents from configuration', function () {
    config(['laravel-auditor.custom_agents' => [
        'my_agent' => [
            'display_name' => 'My Agent',
            'guidelines_path' => 'MYAGENT.md',
            'skills_path' => '.my-agent/skills',
            'mcp_config_path' => '.my-agent/mcp.json',
            'mcp_config_key' => 'servers',
            'detect_files' => ['MYAGENT.md'],
            'detect_paths' => ['.my-agent'],
        ],
    ]]);

    $agent = AgentRegistry::find('my_agent');

    expect($agent)->toBeInstanceOf(Agent::class);
    expect($agent?->displayName)->toBe('My Agent');
    expect($agent?->guidelinesPath)->toBe('MYAGENT.md');
    expect($agent?->skillsPath)->toBe('.my-agent/skills');
    expect($agent?->mcpConfigPath)->toBe('.my-agent/mcp.json');
    expect($agent?->mcpConfigKey)->toBe('servers');
    expect($agent?->detectFiles)->toBe(['MYAGENT.md']);
    expect($agent?->detectPaths)->toBe(['.my-agent']);
    expect($agent?->supportsMcp())->toBeTrue();
});

it('lets a custom agent override a built-in key', function () {
    config(['laravel-auditor.custom_agents' => [
        'claude_code' => [
            'display_name' => 'Custom Claude',
            'guidelines_path' => 'CUSTOM.md',
            'skills_path' => '.custom/skills',
        ],
    ]]);

    $agent = AgentRegistry::find('claude_code');

    expect($agent?->displayName)->toBe('Custom Claude');
    expect($agent?->guidelinesPath)->toBe('CUSTOM.md');
});

it('ignores incomplete or invalid custom agent definitions', function () {
    config(['laravel-auditor.custom_agents' => 'nope']);
    expect(AgentRegistry::custom())->toBe([]);

    config(['laravel-auditor.custom_agents' => [
        '' => [
            'guidelines_path' => 'AGENTS.md',
            'skills_path' => '.agents/skills',
        ],
        'missing_skills' => [
            'guidelines_path' => 'AGENTS.md',
        ],
        'missing_guidelines' => [
            'skills_path' => '.agents/skills',
        ],
        'not_an_array' => 'nope',
        'non_string_paths' => [
            'guidelines_path' => ['AGENTS.md'],
            'skills_path' => ['.agents/skills'],
            'display_name' => ['Nope'],
            'mcp_config_path' => ['overlay.yml'],
            'mcp_config_key' => ['mcp'],
        ],
        12 => [
            'guidelines_path' => 'AGENTS.md',
            'skills_path' => '.agents/skills',
        ],
    ]]);

    $custom = AgentRegistry::custom();

    expect($custom)->toHaveKey('12');
    expect($custom)->not->toHaveKey('missing_skills');
    expect($custom)->not->toHaveKey('missing_guidelines');
    expect($custom)->not->toHaveKey('non_string_paths');
    expect(AgentRegistry::fromConfig('  ', [
        'guidelines_path' => 'AGENTS.md',
        'skills_path' => '.agents/skills',
    ]))->toBeNull();
});

it('normalizes optional custom agent fields', function () {
    $agent = AgentRegistry::fromConfig('my_agent', [
        'guidelines_path' => 'AGENTS.md',
        'skills_path' => '.my-agent/skills',
        'display_name' => '  ',
        'mcp_config_path' => '  ',
        'mcp_config_key' => '  ',
        'detect_files' => 'MYAGENT.md',
        'detect_paths' => ['', ' .my-agent ', 12, null],
    ]);

    expect($agent)->toBeInstanceOf(Agent::class);
    expect($agent?->displayName)->toBe('my_agent');
    expect($agent?->mcpConfigPath)->toBeNull();
    expect($agent?->mcpConfigKey)->toBe('mcpServers');
    expect($agent?->detectFiles)->toBe([]);
    expect($agent?->detectPaths)->toBe(['.my-agent']);
    expect($agent?->supportsMcp())->toBeFalse();
});

it('treats only json and toml mcp paths as supported', function () {
    $json = AgentRegistry::fromConfig('json', [
        'guidelines_path' => 'AGENTS.md',
        'skills_path' => '.a/skills',
        'mcp_config_path' => '.a/mcp.json',
    ]);
    $toml = AgentRegistry::fromConfig('toml', [
        'guidelines_path' => 'AGENTS.md',
        'skills_path' => '.a/skills',
        'mcp_config_path' => '.a/config.toml',
    ]);
    $yaml = AgentRegistry::fromConfig('yaml', [
        'guidelines_path' => 'AGENTS.md',
        'skills_path' => '.a/skills',
        'mcp_config_path' => '.a/overlay.yml',
    ]);

    expect($json?->supportsMcp())->toBeTrue();
    expect($toml?->supportsMcp())->toBeTrue();
    expect($yaml?->supportsMcp())->toBeFalse();
    expect(AgentRegistry::find('gemini')?->supportsMcp())->toBeFalse();
});
