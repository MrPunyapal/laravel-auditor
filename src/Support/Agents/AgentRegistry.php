<?php

declare(strict_types=1);

namespace LaravelAuditor\Support\Agents;

use Illuminate\Support\Collection;

/**
 * Registry of AI agents the standalone installer can target.
 *
 * Mirrors the agents supported by Laravel Boost so a project wired with
 * either tool ends up with equivalent skills, guidelines, and MCP config.
 */
final class AgentRegistry
{
    /**
     * @return array<string, Agent>
     */
    public static function all(): array
    {
        return [
            'opencode' => new Agent(
                name: 'opencode',
                displayName: 'OpenCode',
                guidelinesPath: 'AGENTS.md',
                skillsPath: '.agents/skills',
                mcpConfigPath: 'opencode.json',
                mcpConfigKey: 'mcp',
                detectFiles: ['opencode.json', 'opencode.jsonc'],
                detectPaths: ['.agents'],
            ),
            'claude_code' => new Agent(
                name: 'claude_code',
                displayName: 'Claude Code',
                guidelinesPath: 'CLAUDE.md',
                skillsPath: '.claude/skills',
                mcpConfigPath: '.mcp.json',
                detectFiles: ['CLAUDE.md'],
                detectPaths: ['.claude'],
            ),
            'cursor' => new Agent(
                name: 'cursor',
                displayName: 'Cursor',
                guidelinesPath: '.cursor/rules/laravel-auditor.mdc',
                skillsPath: '.cursor/skills',
                mcpConfigPath: '.cursor/mcp.json',
                detectPaths: ['.cursor'],
            ),
            'copilot' => new Agent(
                name: 'copilot',
                displayName: 'GitHub Copilot',
                guidelinesPath: '.github/copilot-instructions.md',
                skillsPath: '.github/skills',
                mcpConfigPath: '.vscode/mcp.json',
                mcpConfigKey: 'servers',
                detectFiles: ['.github/copilot-instructions.md'],
            ),
            'gemini' => new Agent(
                name: 'gemini',
                displayName: 'Gemini CLI',
                guidelinesPath: 'GEMINI.md',
                skillsPath: '.gemini/skills',
                mcpConfigPath: null,
                detectFiles: ['GEMINI.md'],
                detectPaths: ['.gemini'],
            ),
            'codex' => new Agent(
                name: 'codex',
                displayName: 'Codex',
                guidelinesPath: 'AGENTS.md',
                skillsPath: '.agents/skills',
                mcpConfigPath: '.codex/config.toml',
                mcpConfigKey: 'mcp_servers',
                detectPaths: ['.codex'],
            ),
            'junie' => new Agent(
                name: 'junie',
                displayName: 'Junie',
                guidelinesPath: 'AGENTS.md',
                skillsPath: '.junie/skills',
                mcpConfigPath: '.junie/mcp/mcp.json',
                detectPaths: ['.junie'],
            ),
            'zed' => new Agent(
                name: 'zed',
                displayName: 'Zed',
                guidelinesPath: 'AGENTS.md',
                skillsPath: '.agents/skills',
                mcpConfigPath: '.zed/settings.json',
                mcpConfigKey: 'context_servers',
                detectPaths: ['.zed'],
            ),
        ];
    }

    public static function find(string $name): ?Agent
    {
        return self::all()[$name] ?? null;
    }

    /**
     * @param  list<string>  $names
     * @return Collection<int, Agent>
     */
    public static function resolve(array $names): Collection
    {
        return collect($names)
            ->map(fn (string $name): ?Agent => self::find($name))
            ->filter()
            ->values();
    }
}
