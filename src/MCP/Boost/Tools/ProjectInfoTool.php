<?php

declare(strict_types=1);

namespace LaravelAuditor\MCP\Boost\Tools;

use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use LaravelAuditor\Context\Collectors\ProjectInfoCollector;

#[IsReadOnly]
final class ProjectInfoTool extends AuditTool
{
    public function __construct(ProjectInfoCollector $collector)
    {
        parent::__construct($collector);
    }
}
