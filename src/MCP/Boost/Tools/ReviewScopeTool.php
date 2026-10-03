<?php

declare(strict_types=1);

namespace LaravelAuditor\MCP\Boost\Tools;

use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use LaravelAuditor\Context\Collectors\ReviewScopeCollector;

#[IsReadOnly]
final class ReviewScopeTool extends AuditTool
{
    public function __construct(ReviewScopeCollector $collector)
    {
        parent::__construct($collector);
    }
}
