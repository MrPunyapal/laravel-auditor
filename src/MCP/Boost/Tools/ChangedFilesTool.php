<?php

declare(strict_types=1);

namespace LaravelAuditor\MCP\Boost\Tools;

use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use LaravelAuditor\Context\Collectors\ChangedFilesCollector;

#[IsReadOnly]
final class ChangedFilesTool extends AuditTool
{
    public function __construct(ChangedFilesCollector $collector)
    {
        parent::__construct($collector);
    }
}
