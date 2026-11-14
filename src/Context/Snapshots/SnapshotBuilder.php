<?php

declare(strict_types=1);

namespace LaravelAuditor\Context\Snapshots;

use LaravelAuditor\Context\ContextRegistry;
use RuntimeException;

/**
 * Captures a stable, source-free view of route and authorization metadata.
 */
final class SnapshotBuilder
{
    public const string FORMAT = 'laravel-auditor-snapshot';

    public const int VERSION = 1;

    public function __construct(
        private readonly ContextRegistry $context,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function capture(bool $withSchema = false): array
    {
        $routes = $this->context->get('routes')->collect();
        $authorization = $this->context->get('policies_authorization')->collect();

        if (! is_array($routes['routes'] ?? null)) {
            throw new RuntimeException('Route collector did not return a route list.');
        }

        $records = [];

        foreach ($routes['routes'] as $route) {
            if (! is_array($route) || ! is_array($route['methods'] ?? null) || ! is_array($route['middleware'] ?? null)) {
                throw new RuntimeException('Route collector returned an invalid route.');
            }

            $methods = array_values(array_unique(array_map('strtoupper', $route['methods'])));
            sort($methods, SORT_STRING);

            $records[] = [
                'methods' => $methods,
                'uri' => (string) ($route['uri'] ?? ''),
                'domain' => isset($route['domain']) ? (string) $route['domain'] : null,
                'name' => isset($route['name']) ? (string) $route['name'] : null,
                'action' => isset($route['action']) ? (string) $route['action'] : null,
                // Middleware order can affect behavior, so do not sort it.
                'middleware' => array_values(array_map('strval', $route['middleware'])),
            ];
        }

        usort($records, static fn (array $a, array $b): int => strcmp(
            json_encode([$a['domain'], $a['uri'], $a['methods'], $a['name'], $a['action']], JSON_THROW_ON_ERROR),
            json_encode([$b['domain'], $b['uri'], $b['methods'], $b['name'], $b['action']], JSON_THROW_ON_ERROR),
        ));

        foreach (['gates', 'policies', 'policy_files', 'middleware'] as $field) {
            if (! is_array($authorization[$field] ?? null)) {
                throw new RuntimeException("Authorization collector did not return [{$field}].");
            }
        }

        $gates = array_values(array_map('strval', $authorization['gates']));
        $files = array_values(array_map('strval', $authorization['policy_files']));
        $middleware = array_values(array_map('strval', $authorization['middleware']));
        $policies = $authorization['policies'];

        sort($gates, SORT_STRING);
        sort($files, SORT_STRING);
        sort($middleware, SORT_STRING);
        ksort($policies, SORT_STRING);

        $sections = [
            'routes' => $records,
            'authorization' => [
                'gates' => $gates,
                'policies' => $policies,
                'policy_files' => $files,
                'middleware_aliases' => $middleware,
            ],
        ];

        if ($withSchema) {
            $sections['schema'] = $this->schema();
        }

        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'sections' => $sections,
        ];
    }
    /**
     * Collect schema shape only: never persist database names, data, or column defaults.
     *
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        $payload = $this->context->get('database_schema')->collect();

        if (($payload['available'] ?? null) !== true || ! is_array($payload['tables'] ?? null)) {
            throw new RuntimeException('Database schema is unavailable; a complete schema snapshot cannot be captured.');
        }

        $tables = [];

        foreach ($payload['tables'] as $table) {
            if (! is_array($table) || ! is_string($table['name'] ?? null) ||
                ! is_array($table['columns'] ?? null) || ! is_array($table['indexes'] ?? null) ||
                ! is_array($table['foreign_keys'] ?? null)) {
                throw new RuntimeException('Database schema collector returned invalid table metadata.');
            }

            $columns = [];

            foreach ($table['columns'] as $column) {
                if (! is_array($column)) {
                    throw new RuntimeException('Database schema collector returned an invalid column.');
                }

                $columns[] = [
                    'name' => (string) ($column['name'] ?? ''),
                    'type' => (string) ($column['type'] ?? ''),
                    'nullable' => (bool) ($column['nullable'] ?? false),
                ];
            }

            $indexes = [];

            foreach ($table['indexes'] as $index) {
                if (! is_array($index)) {
                    throw new RuntimeException('Database schema collector returned an invalid index.');
                }

                $indexes[] = [
                    'name' => (string) ($index['name'] ?? ''),
                    'columns' => array_values(array_map('strval', (array) ($index['columns'] ?? []))),
                    'unique' => (bool) ($index['unique'] ?? false),
                ];
            }

            $foreignKeys = [];

            foreach ($table['foreign_keys'] as $key) {
                if (! is_array($key)) {
                    throw new RuntimeException('Database schema collector returned an invalid foreign key.');
                }

                $foreignKeys[] = [
                    'name' => isset($key['name']) ? (string) $key['name'] : null,
                    'columns' => array_values(array_map('strval', (array) ($key['columns'] ?? []))),
                    'foreign_table' => (string) ($key['foreign_table'] ?? ''),
                    'foreign_columns' => array_values(array_map('strval', (array) ($key['foreign_columns'] ?? []))),
                    'on_update' => isset($key['on_update']) ? (string) $key['on_update'] : null,
                    'on_delete' => isset($key['on_delete']) ? (string) $key['on_delete'] : null,
                ];
            }

            usort($columns, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));
            usort($indexes, static fn (array $a, array $b): int => strcmp(json_encode($a, JSON_THROW_ON_ERROR), json_encode($b, JSON_THROW_ON_ERROR)));
            usort($foreignKeys, static fn (array $a, array $b): int => strcmp(json_encode($a, JSON_THROW_ON_ERROR), json_encode($b, JSON_THROW_ON_ERROR)));

            $tables[] = [
                'name' => $table['name'],
                'columns' => $columns,
                'indexes' => $indexes,
                'foreign_keys' => $foreignKeys,
            ];
        }

        usort($tables, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return [
            'driver' => (string) ($payload['driver'] ?? ''),
            'tables' => $tables,
        ];
    }

}
