<?php

declare(strict_types=1);

namespace LaravelAuditor\MCP\Boost\Tools;

use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use LaravelAuditor\Context\ContextCollector;
use Throwable;

/**
 * Base adapter exposing a context collector as a Laravel Boost MCP tool.
 */
abstract class AuditTool extends Tool
{
    public function __construct(private readonly ContextCollector $collector)
    {
        $this->name = $collector->name();
        $this->title = Str::headline(str_replace('_', ' ', $collector->name()));
        $this->description = $collector->description();
    }

    public function handle(Request $request): Response
    {
        try {
            return Response::json($this->collector->collect());
        } catch (Throwable $throwable) {
            return Response::error('Error collecting context: '.$throwable->getMessage());
        }
    }
}
