<?php

declare(strict_types=1);

namespace LaravelAuditor\MCP\Boost\Tools;

use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use LaravelAuditor\Context\Collectors\TestsCollector;

#[IsReadOnly]
final class TestsTool extends AuditTool
{
    public function __construct(TestsCollector $collector)
    {
        parent::__construct($collector);
    }
}
