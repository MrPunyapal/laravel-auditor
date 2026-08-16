<?php

declare(strict_types=1);

namespace LaravelAuditor\MCP\Boost\Tools;

use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use LaravelAuditor\Context\Collectors\AuthorizationCollector;

#[IsReadOnly]
final class AuthorizationTool extends AuditTool
{
    public function __construct(AuthorizationCollector $collector)
    {
        parent::__construct($collector);
    }
}
