<?php

declare(strict_types=1);

namespace LaravelAuditor\Context;

/**
 * A read-only collector that produces structured, deterministic context about
 * a Laravel application. Collectors power the MCP tools and the report model.
 */
interface ContextCollector
{
    /**
     * The stable tool/collector name (snake_case).
     */
    public function name(): string;

    /**
     * A short description used to expose the collector to agents.
     */
    public function description(): string;

    /**
     * Collect and return structured context. Must be safe and read-only.
     *
     * @return array<string, mixed>
     */
    public function collect(): array;
}
