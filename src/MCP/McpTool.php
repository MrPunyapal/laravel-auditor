<?php

declare(strict_types=1);

namespace LaravelAuditor\MCP;

use LaravelAuditor\Context\ContextCollector;
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
     * @param  array<string, mixed>  $arguments
     * @return array{content: list<array{type: string, text: string}>, isError?: bool}
     */
    public function call(array $arguments = []): array
    {
        try {
            $result = $this->collector->collect();

            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => (string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
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
}
