<?php

declare(strict_types=1);

namespace LaravelAuditor\MCP\Boost\Tools;

use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use LaravelAuditor\Context\Collectors\DatabaseSchemaCollector;

#[IsReadOnly]
final class DatabaseSchemaTool extends AuditTool
{
    public function __construct(DatabaseSchemaCollector $collector)
    {
        parent::__construct($collector);
    }
}
