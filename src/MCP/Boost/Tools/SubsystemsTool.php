<?php

declare(strict_types=1);

namespace LaravelAuditor\MCP\Boost\Tools;

use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use LaravelAuditor\Context\Collectors\SubsystemsCollector;

#[IsReadOnly]
final class SubsystemsTool extends AuditTool
{
    public function __construct(SubsystemsCollector $collector)
    {
        parent::__construct($collector);
    }
}
