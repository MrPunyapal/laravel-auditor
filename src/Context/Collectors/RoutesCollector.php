<?php

declare(strict_types=1);

namespace LaravelAuditor\Context\Collectors;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use LaravelAuditor\Context\ContextCollector;

/**
 * Lists the application's registered routes with their handlers.
 */
final class RoutesCollector implements ContextCollector
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
        return 'List the application routes with methods, URIs, names, and controllers.';
    }

    /**
     * @return array<string, mixed>
     */
    public function collect(): array
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

        return [
            'count' => count($routes),
            'routes' => $routes,
        ];
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
