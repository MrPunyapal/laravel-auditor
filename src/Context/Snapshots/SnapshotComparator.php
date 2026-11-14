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

        $beforeOrder = $this->routeOrder($before['sections']['route_order'], $oldRoutes);
        $afterOrder = $this->routeOrder($after['sections']['route_order'], $newRoutes);
        $sharedRoutes = array_intersect_key($oldRoutes, $newRoutes);
        $beforeShared = array_values(array_filter($beforeOrder, static fn (string $id): bool => isset($sharedRoutes[$id])));
        $afterShared = array_values(array_filter($afterOrder, static fn (string $id): bool => isset($sharedRoutes[$id])));
        $orderChanged = $beforeShared !== $afterShared;

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

        return [
            'format' => 'laravel-auditor-snapshot-diff',
            'version' => SnapshotBuilder::VERSION,
            'complete' => true,
            'has_changes' => $added !== [] || $removed !== [] || $modified !== [] || $authChanged || $orderChanged,
            'summary' => [
                'routes_added' => count($added),
                'routes_removed' => count($removed),
                'routes_modified' => count($modified),
                'route_order_changed' => $orderChanged,
            ],
            'changes' => [
                'routes' => ['added' => $added, 'removed' => $removed, 'modified' => $modified],
                'authorization' => $authorization,
                'route_order' => [
                    'changed' => $orderChanged,
                    'before' => $orderChanged ? $this->routesInOrder($beforeShared, $oldRoutes) : [],
                    'after' => $orderChanged ? $this->routesInOrder($afterShared, $newRoutes) : [],
                ],
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

        if (! is_array($sections) || ! is_array($sections['routes'] ?? null) || ! array_is_list($sections['routes']) ||
            ! is_array($sections['route_order'] ?? null) || ! array_is_list($sections['route_order'])) {
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

        foreach (['gates', 'policy_files', 'middleware_aliases'] as $field) {
            if (! array_is_list($authorization[$field])) {
                throw new RuntimeException("Snapshot authorization [{$field}] must be a list.");
            }

            foreach ($authorization[$field] as $item) {
                if (! is_string($item)) {
                    throw new RuntimeException("Snapshot authorization [{$field}] contains an invalid entry.");
                }
            }
        }

        foreach ($authorization['policies'] as $model => $policy) {
            if (! is_string($model) || ! is_string($policy)) {
                throw new RuntimeException('Snapshot contains an invalid policy mapping.');
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
                ! is_array($route['methods'] ?? null) || ! is_array($route['middleware'] ?? null) ||
                ! is_array($route['resolved_middleware'] ?? null)) {
                throw new RuntimeException('Snapshot contains invalid route metadata.');
            }

            foreach (['domain', 'name', 'action'] as $field) {
                if (! array_key_exists($field, $route) || (! is_string($route[$field]) && $route[$field] !== null)) {
                    throw new RuntimeException("Snapshot route has an invalid [{$field}] field.");
                }
            }

            if (! array_is_list($route['methods']) || $route['methods'] === [] ||
                ! array_is_list($route['middleware']) || ! array_is_list($route['resolved_middleware'])) {
                throw new RuntimeException('Snapshot route methods or middleware are not complete lists.');
            }

            foreach ([...$route['methods'], ...$route['middleware'], ...$route['resolved_middleware']] as $value) {
                if (! is_string($value) || $value === '') {
                    throw new RuntimeException('Snapshot route contains an invalid method or middleware.');
                }
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
     * @param  list<mixed>  $order
     * @param  array<string, array<string, mixed>>  $routes
     * @return list<string>
     */
    private function routeOrder(array $order, array $routes): array
    {
        $keys = [];

        foreach ($order as $entry) {
            if (! is_array($entry) || ! is_string($entry['uri'] ?? null) ||
                ! array_key_exists('domain', $entry) ||
                (! is_string($entry['domain']) && $entry['domain'] !== null) ||
                ! is_array($entry['methods'] ?? null) || ! array_is_list($entry['methods']) ||
                $entry['methods'] === []) {
                throw new RuntimeException('Snapshot has invalid route registration order.');
            }

            foreach ($entry['methods'] as $method) {
                if (! is_string($method) || $method === '') {
                    throw new RuntimeException('Snapshot has invalid method in route registration order.');
                }
            }

            $id = json_encode([$entry['domain'], $entry['uri'], $entry['methods']], JSON_THROW_ON_ERROR);

            if (! isset($routes[$id]) || in_array($id, $keys, true)) {
                throw new RuntimeException('Snapshot registration order does not match the route records.');
            }

            $keys[] = $id;
        }

        if (count($keys) !== count($routes)) {
            throw new RuntimeException('Snapshot registration order omits route records.');
        }

        return $keys;
    }

    /**
     * @param  list<string>  $keys
     * @param  array<string, array<string, mixed>>  $routes
     * @return list<array{domain: string|null, uri: string, methods: list<string>}>
     */
    private function routesInOrder(array $keys, array $routes): array
    {
        $result = [];

        foreach ($keys as $key) {
            $route = $routes[$key];

            $result[] = [
                'domain' => $route['domain'],
                'uri' => $route['uri'],
                'methods' => $route['methods'],
            ];
        }

        return $result;
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
