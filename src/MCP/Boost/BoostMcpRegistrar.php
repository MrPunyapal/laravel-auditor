<?php

declare(strict_types=1);

namespace LaravelAuditor\MCP\Boost;

use LaravelAuditor\MCP\Boost\Tools\AuditTool;
use LaravelAuditor\MCP\Boost\Tools\AuthorizationTool;
use LaravelAuditor\MCP\Boost\Tools\ChangedFilesTool;
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
     * Collector name to Boost tool class map. Keep in sync with
     * ContextRegistry: every collector needs exactly one entry here,
     * otherwise the Boost transport silently omits it.
     *
     * @var array<string, class-string<AuditTool>>
     */
    private const array COLLECTOR_TOOLS = [
        'project_info' => ProjectInfoTool::class,
        'routes' => RoutesTool::class,
        'models' => ModelsTool::class,
        'migrations' => MigrationsTool::class,
        'database_schema' => DatabaseSchemaTool::class,
        'dependencies' => DependenciesTool::class,
        'configuration' => ConfigurationTool::class,
        'policies_authorization' => AuthorizationTool::class,
        'jobs_events_schedules' => JobsEventsSchedulesTool::class,
        'tests' => TestsTool::class,
        'subsystems' => SubsystemsTool::class,
        'changed_files' => ChangedFilesTool::class,
    ];

    /**
     * @return array<int, class-string<AuditTool>>
     */
    public function toolClasses(): array
    {
        return array_values(self::COLLECTOR_TOOLS);
    }

    /**
     * @return array<string, class-string<AuditTool>> Keyed by collector name.
     */
    public static function collectorToolMap(): array
    {
        return self::COLLECTOR_TOOLS;
    }
}
