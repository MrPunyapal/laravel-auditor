<?php

declare(strict_types=1);

namespace LaravelAuditor\MCP;

use InvalidArgumentException;
use LaravelAuditor\Context\ContextCollector;
use LaravelAuditor\Context\FilterableCollector;

/**
 * Validates MCP filter arguments and applies them to collectors.
 *
 * Single home for the filter policy shared by the stdio server and the
 * Boost tool adapter so the two transports cannot diverge.
 */
final class FilterValidator
{
    /**
     * Validates raw tool arguments against a filterable collector's declared
     * filters and normalizes every value to a string.
     *
     * Unknown filters are rejected instead of silently ignored so a typo can
     * never masquerade as an unfiltered (full) result. Null values are
     * treated as absent.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, string>
     */
    public static function validate(FilterableCollector $collector, array $arguments): array
    {
        $declared = $collector->filters();
        $normalized = [];

        foreach ($arguments as $key => $value) {
            if (! array_key_exists($key, $declared)) {
                throw new InvalidArgumentException(sprintf(
                    'Unknown filter [%s] for tool [%s]. Accepted filters: %s.',
                    $key,
                    $collector->name(),
                    implode(', ', array_keys($declared)),
                ));
            }

            if ($value === null) {
                continue;
            }

            if (is_array($value)) {
                throw new InvalidArgumentException("Filter [{$key}] must be a single value.");
            }

            $normalized[$key] = (string) $value;
        }

        return $normalized;
    }

    /**
     * Collect with validated filter arguments, falling back to the full
     * inventory when normalization yields no usable filter.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public static function collectWithFilters(ContextCollector $collector, array $arguments): array
    {
        if ($collector instanceof FilterableCollector && $arguments !== []) {
            $filters = self::validate($collector, $arguments);

            if ($filters !== []) {
                return $collector->collectFiltered($filters);
            }
        }

        return $collector->collect();
    }

    /**
     * Filter properties shared by both transports' tool schemas.
     *
     * @return array<string, string> Filter name to human description.
     */
    public static function properties(FilterableCollector $collector): array
    {
        return $collector->filters();
    }
}
