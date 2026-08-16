<?php

declare(strict_types=1);

namespace LaravelAuditor\MCP\Boost;

use LaravelAuditor\MCP\Boost\Tools\AuditTool;
use LaravelAuditor\MCP\Boost\Tools\AuthorizationTool;
use LaravelAuditor\MCP\Boost\Tools\ConfigurationTool;
use LaravelAuditor\MCP\Boost\Tools\DatabaseSchemaTool;
use LaravelAuditor\MCP\Boost\Tools\DependenciesTool;
use LaravelAuditor\MCP\Boost\Tools\JobsEventsSchedulesTool;
use LaravelAuditor\MCP\Boost\Tools\MigrationsTool;
use LaravelAuditor\MCP\Boost\Tools\ModelsTool;
use LaravelAuditor\MCP\Boost\Tools\ProjectInfoTool;
use LaravelAuditor\MCP\Boost\Tools\RoutesTool;
use LaravelAuditor\MCP\Boost\Tools\SubsystemsTool;
use LaravelAuditor\MCP\Boost\Tools\TestsTool;
use LaravelAuditor\Support\BoostDetector;

/**
 * Registers Laravel Auditor's context collectors as tools inside Laravel Boost's
 * MCP server by merging them into the boost.mcp.tools.include configuration.
 */
final class BoostMcpRegistrar
{
    public function __construct(private readonly BoostDetector $detector) {}

    public function register(): void
    {
        if (! $this->detector->isInstalled()) {
            return;
        }

        $include = array_values(array_unique([
            ...(array) config('boost.mcp.tools.include', []),
            ...$this->toolClasses(),
        ]));

        config(['boost.mcp.tools.include' => $include]);
    }

    /**
     * @return array<int, class-string<AuditTool>>
     */
    public function toolClasses(): array
    {
        return [
            ProjectInfoTool::class,
            RoutesTool::class,
            ModelsTool::class,
            MigrationsTool::class,
            DatabaseSchemaTool::class,
            DependenciesTool::class,
            ConfigurationTool::class,
            AuthorizationTool::class,
            JobsEventsSchedulesTool::class,
            TestsTool::class,
            SubsystemsTool::class,
        ];
    }
}
