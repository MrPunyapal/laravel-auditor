<?php

declare(strict_types=1);

namespace LaravelAuditor\Context\Collectors;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use LaravelAuditor\Context\ContextCollector;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Inspects the application's Eloquent models and their declared metadata.
 *
 * Model metadata (table, fillable, guarded, casts) is read from the model's
 * public API rather than guessed. Relationship methods are detected via
 * reflection and reported without invoking queries.
 */
final class ModelsCollector implements ContextCollector
{
    public function __construct(
        private readonly Application $app,
        private readonly Filesystem $files,
    ) {}

    public function name(): string
    {
        return 'models';
    }

    public function description(): string
    {
        return 'Inspect Eloquent models: table names, fillable/guarded, casts, and declared relationships.';
    }

    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $models = [];

        foreach ($this->discoverModels() as $class) {
            $models[] = $this->inspect($class);
        }

        usort($models, static fn (array $a, array $b): int => strcmp((string) $a['class'], (string) $b['class']));

        return [
            'count' => count($models),
            'models' => $models,
        ];
    }

    /**
     * @return list<string>
     */
    private function discoverModels(): array
    {
        $directory = app_path('Models');

        if (! $this->files->isDirectory($directory)) {
            return [];
        }

        $models = [];

        foreach ($this->files->allFiles($directory) as $file) {
            $relative = $file->getRelativePathname();
            $class = $this->app->getNamespace().'Models\\'.str_replace(['/', '.php'], ['\\', ''], $relative);

            if ($this->isEloquentModel($class)) {
                $models[] = $class;
            }
        }

        return $models;
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
