<?php

declare(strict_types=1);

namespace LaravelAuditor\MCP\Boost\Tools;

use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use LaravelAuditor\Context\Collectors\JobsEventsSchedulesCollector;

#[IsReadOnly]
final class JobsEventsSchedulesTool extends AuditTool
{
    public function __construct(JobsEventsSchedulesCollector $collector)
    {
        parent::__construct($collector);
    }
}
