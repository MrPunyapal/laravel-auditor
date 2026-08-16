<?php

declare(strict_types=1);

namespace LaravelAuditor\MCP\Boost\Tools;

use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use LaravelAuditor\Context\Collectors\MigrationsCollector;

#[IsReadOnly]
final class MigrationsTool extends AuditTool
{
    public function __construct(MigrationsCollector $collector)
    {
        parent::__construct($collector);
    }
}
