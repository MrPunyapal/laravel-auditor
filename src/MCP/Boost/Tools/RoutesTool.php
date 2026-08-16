<?php

declare(strict_types=1);

namespace LaravelAuditor\MCP\Boost\Tools;

use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use LaravelAuditor\Context\Collectors\RoutesCollector;

#[IsReadOnly]
final class RoutesTool extends AuditTool
{
    public function __construct(RoutesCollector $collector)
    {
        parent::__construct($collector);
    }
}
