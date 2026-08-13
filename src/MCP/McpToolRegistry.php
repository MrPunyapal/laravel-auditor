<?php

declare(strict_types=1);

namespace LaravelAuditor\MCP;

use LaravelAuditor\Context\ContextRegistry;

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
            );
        }

        usort($tools, static fn (McpTool $a, McpTool $b): int => strcmp($a->name, $b->name));

        return $tools;
    }
}
