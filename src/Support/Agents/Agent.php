<?php

declare(strict_types=1);

namespace LaravelAuditor\Support\Agents;

/**
 * Describes a supported AI agent and where its resources live in a project.
 *
 * Each agent carries its own convention for guideline files, native skill
 * directories, and MCP server configuration so the installer can wire the
 * right resources to the right tool instead of writing everything everywhere.
 */
final readonly class Agent
{
    /**
     * @param  list<string>  $detectFiles  Project markers that indicate the agent is used.
     * @param  list<string>  $detectPaths  Project marker directories.
     */
    public function __construct(
        public string $name,
        public string $displayName,
        public string $guidelinesPath,
        public string $skillsPath,
        public ?string $mcpConfigPath,
        public string $mcpConfigKey = 'mcpServers',
        public array $detectFiles = [],
        public array $detectPaths = [],
    ) {}

    public function supportsMcp(): bool
    {
        return $this->mcpConfigPath !== null;
    }
}
