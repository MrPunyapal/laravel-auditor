<?php

declare(strict_types=1);

namespace LaravelAuditor\MCP\Boost\Tools;

use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use LaravelAuditor\Context\Collectors\ModelsCollector;

#[IsReadOnly]
final class ModelsTool extends AuditTool
{
    public function __construct(ModelsCollector $collector)
    {
        parent::__construct($collector);
    }
}
