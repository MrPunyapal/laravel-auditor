<?php

declare(strict_types=1);

namespace LaravelAuditor\Context\Collectors;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Filesystem\Filesystem;
use LaravelAuditor\Context\ContextCollector;
use ReflectionClass;
use SplFileInfo;
use Throwable;

/**
 * Collects authorization context: registered gates and policy files.
 *
 * The gate is resolved lazily and defensively because the authorization
 * stack is not always available (e.g. minimal console contexts).
 */
final class AuthorizationCollector implements ContextCollector
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly ?Gate $gate = null,
    ) {}

    public function name(): string
    {
        return 'policies_authorization';
    }

    public function description(): string
    {
        return 'List registered gates, policy classes, and authorization-related files.';
    }

    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        return [
            'gates' => $this->gates(),
            'policies' => $this->policies(),
            'policy_files' => $this->policyFiles(),
            'middleware' => $this->authMiddleware(),
        ];
    }

    /**
     * @return list<string>
     */
    private function gates(): array
    {
        $gate = $this->resolveGate();

        if ($gate === null) {
            return [];
        }

        try {
            $reflection = new ReflectionClass($gate);

            $property = $reflection->getProperty('abilities');

            return array_map('strval', array_keys((array) $property->getValue($gate)));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, string>
     */
    private function policies(): array
    {
        $gate = $this->resolveGate();

        if ($gate === null) {
            return [];
        }

        try {
            $reflection = new ReflectionClass($gate);

            $property = $reflection->getProperty('policies');

            $policies = (array) $property->getValue($gate);

            $result = [];

            foreach ($policies as $model => $policy) {
                if (is_string($policy)) {
                    $result[is_string($model) ? $model : 'unknown'] = $policy;
                }
            }

            ksort($result);

            return $result;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return list<string>
     */
    private function policyFiles(): array
    {
        $directory = app_path('Policies');

        if (! $this->files->isDirectory($directory)) {
            return [];
        }

        return array_values(array_map(
            static fn (SplFileInfo $file): string => $file->getRelativePathname(),
            $this->files->allFiles($directory),
        ));
    }

    /**
     * @return list<string>
     */
    private function authMiddleware(): array
    {
        try {
            $router = app('router');
            $alias = $router->getMiddleware();

            return array_map('strval', array_keys((array) $alias));
        } catch (Throwable) {
            return [];
        }
    }

    private function resolveGate(): ?Gate
    {
        if ($this->gate !== null) {
            return $this->gate;
        }

        try {
            return app(Gate::class);
        } catch (Throwable) {
            return null;
        }
    }
}
