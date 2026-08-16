<?php

declare(strict_types=1);

namespace LaravelAuditor\MCP\Boost\Tools;

use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use LaravelAuditor\Context\Collectors\DependenciesCollector;

#[IsReadOnly]
final class DependenciesTool extends AuditTool
{
    public function __construct(DependenciesCollector $collector)
    {
        parent::__construct($collector);
    }
}
