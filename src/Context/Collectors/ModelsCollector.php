<?php

declare(strict_types=1);

namespace LaravelAuditor\Context\Collectors;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use LaravelAuditor\Context\ContextCollector;
use LaravelAuditor\Context\FilterableCollector;
use LaravelAuditor\Support\ApplicationPaths;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * Inspects the application's Eloquent models and their declared metadata.
 *
 * Model metadata (table, fillable, guarded, casts) is read from the model's
 * public API rather than guessed. Relationship methods are detected via
 * reflection and reported without invoking queries.
 */
final class ModelsCollector implements ContextCollector, FilterableCollector
{
    public function __construct(
        private readonly Application $app,
        private readonly Filesystem $files,
        private readonly ApplicationPaths $paths,
    ) {}

    public function name(): string
    {
        return 'models';
    }

    public function description(): string
    {
        return 'Inspect Eloquent models: table names, fillable/guarded, casts, and declared relationships. Optional filters: class, table (substring).';
    }

    public function filters(): array
    {
        return [
            'class' => 'Case-insensitive substring match on the model class name, e.g. "User" or "App\\\\Models\\\\Us".',
            'table' => 'Case-insensitive substring match on the model table name.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        return $this->buildModelPayload($this->inspectModels());
    }

    public function collectFiltered(array $arguments): array
    {
        $models = $this->inspectModels();

        $filtered = array_values(array_filter(
            $models,
            fn (array $model): bool => $this->matches($model, $arguments),
        ));

        return $this->buildModelPayload($filtered, count($models));
    }

    /**
     * @param  array<string, mixed>  $model
     * @param  array<string, string>  $filters
     */
    private function matches(array $model, array $filters): bool
    {
        foreach ($filters as $key => $needle) {
            $haystack = match ($key) {
                'class' => (string) $model['class'],
                'table' => (string) $model['table'],
                default => null,
            };

            if ($haystack === null) {
                continue;
            }

            if (! str_contains(mb_strtolower($haystack), mb_strtolower($needle))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function inspectModels(): array
    {
        $models = [];

        foreach ($this->discoverModels() as $class) {
            try {
                $models[] = $this->inspect($class);
            } catch (Throwable) {
                // Skip models whose constructor cannot be satisfied in the
                // current container context (e.g. required parameters, side
                // effects). The collector must not crash on one bad model.
            }
        }

        usort($models, static fn (array $a, array $b): int => strcmp((string) $a['class'], (string) $b['class']));

        return $models;
    }

    /**
     * @param  list<array<string, mixed>>  $models
     * @return array<string, mixed>
     */
    private function buildModelPayload(array $models, ?int $totalCount = null): array
    {
        $payload = [
            'count' => count($models),
            'models' => $models,
        ];

        if ($totalCount !== null) {
            $payload['filtered'] = true;
            $payload['total_count'] = $totalCount;
        }

        return $payload;
    }

    /**
     * @return list<string>
     */
    private function discoverModels(): array
    {
        $directories = $this->paths->directories('Models');

        if ($directories === []) {
            $directories = $this->paths->directories();
        }

        $models = [];

        foreach ($directories as $directory) {
            foreach ($this->files->allFiles($directory) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $class = $this->classFromFile($file->getPathname());

                if ($class !== null && $this->isEloquentModel($class)) {
                    $models[] = $class;
                }
            }
        }

        return array_values(array_unique($models));
    }

    private function classFromFile(string $path): ?string
    {
        $contents = $this->files->get($path);

        if (preg_match('/namespace\s+([^;]+);/', $contents, $namespace) !== 1) {
            return null;
        }

        $class = pathinfo($path, PATHINFO_FILENAME);

        return trim($namespace[1]).'\\'.$class;
    }

    private function isEloquentModel(string $class): bool
    {
        if (! class_exists($class)) {
            return false;
        }

        $reflection = new ReflectionClass($class);

        return $reflection->isInstantiable()
            && $reflection->isSubclassOf(Model::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function inspect(string $class): array
    {
        $model = $this->app->make($class);

        $data = [
            'class' => $class,
            'table' => $model->getTable(),
            'fillable' => $model->getFillable(),
            'guarded' => $model->getGuarded(),
            'casts' => array_keys($model->getCasts()),
            'incrementing' => $model->getIncrementing(),
            'timestamps' => $model->usesTimestamps(),
            'primary_key' => $model->getKeyName(),
            'relationships' => $this->relationships($class),
        ];

        if (! $model->usesTimestamps()) {
            unset($data['timestamps']);
        }

        return $data;
    }

    /**
     * @return array<string, string>
     */
    private function relationships(string $class): array
    {
        if (! class_exists($class)) {
            return [];
        }

        $relationships = [];

        $reflection = new ReflectionClass($class);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getNumberOfRequiredParameters() > 0 || $method->isStatic() || $method->getDeclaringClass()->getName() === Model::class) {
                continue;
            }

            $return = $method->getReturnType();

            $returnType = $return instanceof ReflectionNamedType
                ? $return->getName()
                : $this->returnTypeFromDocblock($method);

            $type = $this->relationshipType($returnType);

            if ($type !== null) {
                $relationships[$method->getName()] = $type;
            }
        }

        ksort($relationships);

        return $relationships;
    }

    private function returnTypeFromDocblock(ReflectionMethod $method): ?string
    {
        $docblock = $method->getDocComment();

        if ($docblock === false) {
            return null;
        }

        if (preg_match('/@return\s+\\\\?([A-Za-z0-9_\\\\]+)/', $docblock, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private function relationshipType(?string $returnType): ?string
    {
        if ($returnType === null) {
            return null;
        }

        $returnType = ltrim($returnType, '\\');

        $map = [
            'Illuminate\Database\Eloquent\Relations\BelongsTo' => 'belongsTo',
            'Illuminate\Database\Eloquent\Relations\BelongsToMany' => 'belongsToMany',
            'Illuminate\Database\Eloquent\Relations\HasMany' => 'hasMany',
            'Illuminate\Database\Eloquent\Relations\HasManyThrough' => 'hasManyThrough',
            'Illuminate\Database\Eloquent\Relations\HasOne' => 'hasOne',
            'Illuminate\Database\Eloquent\Relations\HasOneThrough' => 'hasOneThrough',
            'Illuminate\Database\Eloquent\Relations\MorphMany' => 'morphMany',
            'Illuminate\Database\Eloquent\Relations\MorphOne' => 'morphOne',
            'Illuminate\Database\Eloquent\Relations\MorphTo' => 'morphTo',
            'Illuminate\Database\Eloquent\Relations\MorphToMany' => 'morphToMany',
        ];

        if (isset($map[$returnType])) {
            return $map[$returnType];
        }

        if (Str::endsWith($returnType, '\\HasMany') || Str::endsWith($returnType, '\\BelongsTo')) {
            return $returnType;
        }

        return null;
    }
}
