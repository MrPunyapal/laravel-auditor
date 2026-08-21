<?php

declare(strict_types=1);

namespace LaravelAuditor\MCP;

use LaravelAuditor\Context\ContextCollector;
use LaravelAuditor\Context\ContextRegistry;
use LaravelAuditor\Context\FilterableCollector;

/**
 * Builds MCP tool definitions from the package's context collectors.
 */
final class McpToolRegistry
{
    public function __construct(
        private readonly ContextRegistry $context,
    ) {}

    /**
     * @return list<McpTool>
     */
    public function all(): array
    {
        $tools = [];

        foreach ($this->context->all() as $collector) {
            $tools[] = new McpTool(
                name: $collector->name(),
                description: $collector->description(),
                collector: $collector,
                inputSchema: self::inputSchema($collector),
            );
        }

        usort($tools, static fn (McpTool $a, McpTool $b): int => strcmp($a->name, $b->name));

        return $tools;
    }

    /**
     * @return array{type: string, properties: array<string, mixed>}
     */
    private static function inputSchema(ContextCollector $collector): array
    {
        if (! $collector instanceof FilterableCollector) {
            return ['type' => 'object', 'properties' => []];
        }

        $properties = [];

        foreach ($collector->filters() as $name => $description) {
            $properties[$name] = [
                'type' => 'string',
                'description' => $description,
            ];
        }

        return ['type' => 'object', 'properties' => $properties];
    }
}
