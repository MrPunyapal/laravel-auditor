<?php

declare(strict_types=1);

namespace LaravelAuditor\Support\Agents;

use Illuminate\Support\Collection;

/**
 * Registry of AI agents the standalone installer can target.
 *
 * Built-in rows mirror the agents supported by Laravel Boost. Additional
 * targets come from `laravel-auditor.custom_agents` so a project can wire
 * any agent that reads guidelines, skills, or MCP config files.
 */
final class AgentRegistry
{
    /**
     * @return array<string, Agent>
     */
    public static function all(): array
    {
        return array_merge(self::builtIn(), self::custom());
    }

    /**
     * @return array<string, Agent>
     */
    public static function builtIn(): array
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

    /**
     * @return array<string, Agent>
     */
    public static function custom(): array
    {
        $configured = config('laravel-auditor.custom_agents', []);

        if (! is_array($configured)) {
            return [];
        }

        $agents = [];

        foreach ($configured as $name => $definition) {
            $agent = self::fromConfig((string) $name, is_array($definition) ? $definition : []);

            if ($agent instanceof Agent) {
                $agents[$agent->name] = $agent;
            }
        }

        return $agents;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    public static function fromConfig(string $name, array $definition): ?Agent
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        $guidelines = self::stringValue($definition['guidelines_path'] ?? null);
        $skills = self::stringValue($definition['skills_path'] ?? null);

        if ($guidelines === '' || $skills === '') {
            return null;
        }

        $mcp = self::stringValue($definition['mcp_config_path'] ?? null);
        $mcp = $mcp !== '' ? $mcp : null;

        $mcpKey = self::stringValue($definition['mcp_config_key'] ?? null);
        $display = self::stringValue($definition['display_name'] ?? null);

        return new Agent(
            name: $name,
            displayName: $display !== '' ? $display : $name,
            guidelinesPath: $guidelines,
            skillsPath: $skills,
            mcpConfigPath: $mcp,
            mcpConfigKey: $mcpKey !== '' ? $mcpKey : 'mcpServers',
            detectFiles: self::stringList($definition['detect_files'] ?? []),
            detectPaths: self::stringList($definition['detect_paths'] ?? []),
        );
    }

    private static function stringValue(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            if (! is_string($item)) {
                continue;
            }

            $item = trim($item);

            if ($item !== '') {
                $items[] = $item;
            }
        }

        return $items;
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
