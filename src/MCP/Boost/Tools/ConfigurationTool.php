<?php

declare(strict_types=1);

namespace LaravelAuditor\MCP\Boost\Tools;

use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use LaravelAuditor\Context\Collectors\ConfigurationCollector;

#[IsReadOnly]
final class ConfigurationTool extends AuditTool
{
    public function __construct(ConfigurationCollector $collector)
    {
        parent::__construct($collector);
    }
}
