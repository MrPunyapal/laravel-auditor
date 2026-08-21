<?php

declare(strict_types=1);

namespace LaravelAuditor\Context\Collectors;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use LaravelAuditor\Context\ContextCollector;
use LaravelAuditor\Context\FilterableCollector;

/**
 * Lists the application's registered routes with their handlers.
 */
final class RoutesCollector implements ContextCollector, FilterableCollector
{
    public function __construct(
        private readonly Router $router,
    ) {}

    public function name(): string
    {
        return 'routes';
    }

    public function description(): string
    {
        return 'List the application routes with methods, URIs, names, and controllers. Optional filters: uri, name, action (substring), method (exact verb).';
    }

    public function filters(): array
    {
        return [
            'uri' => 'Case-insensitive substring match on the route URI, e.g. "api/" or "users".',
            'name' => 'Case-insensitive substring match on the route name.',
            'action' => 'Case-insensitive substring match on the controller action or closure marker.',
            'method' => 'Exact HTTP verb match (GET, POST, PUT, PATCH, DELETE, HEAD, OPTIONS).',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        return $this->buildRoutePayload($this->allRoutes());
    }

    public function collectFiltered(array $arguments): array
    {
        $all = $this->allRoutes();

        $routes = array_values(array_filter(
            $all,
            fn (array $route): bool => $this->matches($route, $arguments),
        ));

        return $this->buildRoutePayload($routes, count($all));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function allRoutes(): array
    {
        $routes = [];

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            $routes[] = [
                'methods' => $route->methods(),
                'uri' => $route->uri(),
                'name' => $route->getName(),
                'action' => $this->action($route),
                'middleware' => $route->gatherMiddleware(),
                'domain' => $route->getDomain(),
            ];
        }

        usort($routes, static fn (array $a, array $b): int => strcmp((string) $a['uri'], (string) $b['uri']));

        return $routes;
    }

    /**
     * @param  array<string, mixed>  $route
     * @param  array<string, string>  $filters
     */
    private function matches(array $route, array $filters): bool
    {
        foreach ($filters as $key => $needle) {
            $match = match ($key) {
                'uri' => self::contains((string) $route['uri'], $needle),
                'name' => self::contains((string) ($route['name'] ?? ''), $needle),
                'action' => self::contains((string) ($route['action'] ?? ''), $needle),
                'method' => in_array(strtoupper($needle), array_map('strtoupper', (array) $route['methods']), true),
                default => true,
            };

            if (! $match) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $routes
     * @return array<string, mixed>
     */
    private function buildRoutePayload(array $routes, ?int $totalCount = null): array
    {
        $payload = [
            'count' => count($routes),
            'routes' => $routes,
        ];

        if ($totalCount !== null) {
            $payload['filtered'] = true;
            $payload['total_count'] = $totalCount;
        }

        return $payload;
    }

    private static function contains(string $haystack, string $needle): bool
    {
        return str_contains(mb_strtolower($haystack), mb_strtolower($needle));
    }

    private function action(Route $route): ?string
    {
        $action = $route->getAction('uses');

        if (is_callable($action)) {
            if (is_array($action)) {
                return implode('@', array_map(
                    static fn (object|string $part): string => is_object($part) ? $part::class : $part,
                    $action,
                ));
            }

            return 'closure';
        }

        return is_string($action) ? $action : null;
    }
}
