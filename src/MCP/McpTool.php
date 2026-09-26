<?php

declare(strict_types=1);

namespace LaravelAuditor\MCP;

use LaravelAuditor\Context\ContextCollector;
use LaravelAuditor\Context\FilterableCollector;
use Throwable;

/**
 * Exposes a context collector as an MCP tool.
 */
final class McpTool
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly ContextCollector $collector,
        /**
         * @var array<string, mixed>
         */
        public readonly array $inputSchema = ['type' => 'object', 'properties' => []],
    ) {}

    /**
     * @return array{name: string, description: string, inputSchema: array<string, mixed>}
     */
    public function toJson(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'inputSchema' => $this->inputSchema,
        ];
    }

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
    public static function validateFilters(FilterableCollector $collector, array $arguments): array
    {
        return FilterValidator::validate($collector, $arguments);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{content: list<array{type: string, text: string}>, isError?: bool}
     */
    public function call(array $arguments = []): array
    {
        try {
            $result = $this->result($arguments);

            return [
                'content' => [
                    [
                        'type' => 'text',
                        // Compact encoding keeps payloads token-lean without
                        // dropping a single field from the collected data.
                        'text' => (string) json_encode($result, JSON_UNESCAPED_SLASHES),
                    ],
                ],
            ];
        } catch (Throwable $e) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Error collecting context: '.$e->getMessage(),
                    ],
                ],
                'isError' => true,
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function result(array $arguments): array
    {
        return self::collectWithFilters($this->collector, $arguments);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public static function collectWithFilters(ContextCollector $collector, array $arguments): array
    {
        return FilterValidator::collectWithFilters($collector, $arguments);
    }
}
