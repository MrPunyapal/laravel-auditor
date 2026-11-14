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
    public function capture(): array
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

        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'sections' => [
                'routes' => $records,
                'authorization' => [
                    'gates' => $gates,
                    'policies' => $policies,
                    'policy_files' => $files,
                    'middleware_aliases' => $middleware,
                ],
            ],
        ];
    }
}
