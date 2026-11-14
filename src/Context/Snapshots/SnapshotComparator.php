<?php

declare(strict_types=1);

namespace LaravelAuditor\Context\Snapshots;

use RuntimeException;

/**
 * Compares two complete snapshots without inferring security findings.
 */
final class SnapshotComparator
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, mixed>
     */
    public function compare(array $before, array $after): array
    {
        $this->validate($before);
        $this->validate($after);

        $oldRoutes = $this->routeMap($before['sections']['routes']);
        $newRoutes = $this->routeMap($after['sections']['routes']);

        $added = [];
        $removed = [];
        $modified = [];

        foreach ($newRoutes as $key => $route) {
            if (! isset($oldRoutes[$key])) {
                $added[] = $route;
            } elseif ($oldRoutes[$key] !== $route) {
                $modified[] = [
                    'identity' => [
                        'methods' => $route['methods'],
                        'uri' => $route['uri'],
                        'domain' => $route['domain'],
                    ],
                    'before' => $oldRoutes[$key],
                    'after' => $route,
                ];
            }
        }

        foreach ($oldRoutes as $key => $route) {
            if (! isset($newRoutes[$key])) {
                $removed[] = $route;
            }
        }

        $oldAuth = $before['sections']['authorization'];
        $newAuth = $after['sections']['authorization'];

        $authorization = [
            'gates' => $this->listDiff($oldAuth['gates'], $newAuth['gates']),
            'policy_files' => $this->listDiff($oldAuth['policy_files'], $newAuth['policy_files']),
            'middleware_aliases' => $this->listDiff($oldAuth['middleware_aliases'], $newAuth['middleware_aliases']),
            'policies' => $this->policyDiff($oldAuth['policies'], $newAuth['policies']),
        ];

        $authChanged = false;

        foreach ($authorization as $change) {
            if (array_filter($change, static fn (array $items): bool => $items !== []) !== []) {
                $authChanged = true;

                break;
            }
        }

        $schemaChanges = [];
        $schemaSummary = [];
        $schemaChanged = false;

        if (array_key_exists('schema', $before['sections']) || array_key_exists('schema', $after['sections'])) {
            if (! is_array($before['sections']['schema'] ?? null) || ! is_array($after['sections']['schema'] ?? null)) {
                throw new RuntimeException('Both snapshots must include database schema for a complete comparison.');
            }

            $schema = (new SchemaComparator)->compare($before['sections']['schema'], $after['sections']['schema']);
            $schemaChanges = ['schema' => $schema];
            $schemaSummary = [
                'tables_added' => count($schema['added']),
                'tables_removed' => count($schema['removed']),
                'tables_modified' => count($schema['modified']),
            ];
            $schemaChanged = $schema['added'] !== [] || $schema['removed'] !== [] || $schema['modified'] !== [];
        }

        return [
            'format' => 'laravel-auditor-snapshot-diff',
            'version' => SnapshotBuilder::VERSION,
            'complete' => true,
            'has_changes' => $added !== [] || $removed !== [] || $modified !== [] || $authChanged || $schemaChanged,
            'summary' => [
                'routes_added' => count($added),
                'routes_removed' => count($removed),
                'routes_modified' => count($modified),
                ...$schemaSummary,
            ],
            'changes' => [
                'routes' => ['added' => $added, 'removed' => $removed, 'modified' => $modified],
                'authorization' => $authorization,
                ...$schemaChanges,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function validate(array $snapshot): void
    {
        if (($snapshot['format'] ?? null) !== SnapshotBuilder::FORMAT || ($snapshot['version'] ?? null) !== SnapshotBuilder::VERSION) {
            throw new RuntimeException('Unsupported or missing Laravel Auditor snapshot format/version.');
        }

        $sections = $snapshot['sections'] ?? null;

        if (! is_array($sections) || ! is_array($sections['routes'] ?? null) || ! array_is_list($sections['routes'])) {
            throw new RuntimeException('Snapshot has no complete routes section.');
        }

        $authorization = $sections['authorization'] ?? null;

        if (! is_array($authorization)) {
            throw new RuntimeException('Snapshot has no authorization section.');
        }

        foreach (['gates', 'policy_files', 'middleware_aliases', 'policies'] as $field) {
            if (! is_array($authorization[$field] ?? null)) {
                throw new RuntimeException("Snapshot is missing authorization field [{$field}].");
            }
        }
    }

    /**
     * @param  list<mixed>  $routes
     * @return array<string, array<string, mixed>>
     */
    private function routeMap(array $routes): array
    {
        $map = [];

        foreach ($routes as $route) {
            if (! is_array($route) || ! is_string($route['uri'] ?? null) ||
                ! is_array($route['methods'] ?? null) || ! is_array($route['middleware'] ?? null)) {
                throw new RuntimeException('Snapshot contains invalid route metadata.');
            }

            $identity = json_encode([
                $route['domain'] ?? null,
                $route['uri'],
                $route['methods'],
            ], JSON_THROW_ON_ERROR);

            if (isset($map[$identity])) {
                throw new RuntimeException('Snapshot contains duplicate route identities; comparison is ambiguous.');
            }

            $map[$identity] = $route;
        }

        ksort($map, SORT_STRING);

        return $map;
    }

    /**
     * @param  list<string>  $before
     * @param  list<string>  $after
     * @return array{added: list<string>, removed: list<string>}
     */
    private function listDiff(array $before, array $after): array
    {
        $added = array_values(array_diff($after, $before));
        $removed = array_values(array_diff($before, $after));

        sort($added, SORT_STRING);
        sort($removed, SORT_STRING);

        return ['added' => $added, 'removed' => $removed];
    }

    /**
     * @param  array<string, string>  $before
     * @param  array<string, string>  $after
     * @return array<string, array<string, mixed>>
     */
    private function policyDiff(array $before, array $after): array
    {
        $added = array_diff_key($after, $before);
        $removed = array_diff_key($before, $after);
        $modified = [];

        foreach (array_intersect_key($after, $before) as $model => $policy) {
            if ($before[$model] !== $policy) {
                $modified[$model] = ['before' => $before[$model], 'after' => $policy];
            }
        }

        ksort($added, SORT_STRING);
        ksort($removed, SORT_STRING);
        ksort($modified, SORT_STRING);

        return ['added' => $added, 'removed' => $removed, 'modified' => $modified];
    }
}
