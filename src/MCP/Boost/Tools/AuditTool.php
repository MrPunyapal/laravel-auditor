<?php

declare(strict_types=1);

namespace LaravelAuditor\MCP\Boost\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use LaravelAuditor\Context\ContextCollector;
use LaravelAuditor\Context\FilterableCollector;
use LaravelAuditor\MCP\McpTool;
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

    /**
     * Filterable collectors advertise their optional read-only filters so
     * agents can request a focused slice of the inventory.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        if (! $this->collector instanceof FilterableCollector) {
            return [];
        }

        $properties = [];

        foreach ($this->collector->filters() as $name => $description) {
            $properties[$name] = $schema->string()->description($description);
        }

        return $properties;
    }

    public function handle(Request $request): Response
    {
        try {
            $arguments = $request->all();

            if ($this->collector instanceof FilterableCollector && $arguments !== []) {
                $data = $this->collector->collectFiltered(
                    McpTool::validateFilters($this->collector, $arguments),
                );
            } else {
                $data = $this->collector->collect();
            }

            return Response::json($data);
        } catch (Throwable $throwable) {
            return Response::error('Error collecting context: '.$throwable->getMessage());
        }
    }
}
