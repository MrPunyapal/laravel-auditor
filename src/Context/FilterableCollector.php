<?php

declare(strict_types=1);

namespace LaravelAuditor\Context;

/**
 * A context collector that accepts optional read-only filter arguments.
 *
 * Filtering is always additive: calling the collector without arguments must
 * return the exact same payload as `collect()`. Filters exist so an agent can
 * verify a focused slice (one table, one route group) without wading through
 * the full application inventory.
 */
interface FilterableCollector extends ContextCollector
{
    /**
     * The accepted filter arguments as name => human description pairs.
     *
     * Descriptions are advertised verbatim in MCP tool input schemas, so they
     * must explain what the filter matches and what it leaves untouched.
     *
     * @return array<string, string>
     */
    public function filters(): array;

    /**
     * Collect context narrowed by validated filter arguments.
     *
     * Implementations keep every documented top-level key and add
     * `filtered` and `total_count` keys when a filter is applied so the
     * caller always knows how much of the full inventory was returned.
     *
     * @param  array<string, string>  $arguments
     * @return array<string, mixed>
     */
    public function collectFiltered(array $arguments): array;
}
