<?php

declare(strict_types=1);

namespace LaravelAuditor\Support;

use Composer\Autoload\ClassLoader;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;

/**
 * Resolves application source directories from the app namespace PSR-4 map.
 *
 * This finds Workbench models/policies during package tests and the host
 * application's `app/` directory in a normal Laravel install.
 */
final class ApplicationPaths
{
    public function __construct(
        private readonly Application $app,
        private readonly Filesystem $files,
    ) {}

    /**
     * @return list<string>
     */
    public function directories(string $subdirectory = ''): array
    {
        $paths = [];

        foreach ($this->namespaceRoots() as $root) {
            $path = $subdirectory === ''
                ? $root
                : $root.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $subdirectory);

            if ($this->files->isDirectory($path)) {
                $paths[] = $path;
            }
        }

        $fallback = $subdirectory === '' ? app_path() : app_path($subdirectory);

        if ($this->files->isDirectory($fallback)) {
            $paths[] = $fallback;
        }

        return array_values(array_unique($paths));
    }

    public function has(string $subdirectory): bool
    {
        return $this->directories($subdirectory) !== [];
    }

    public function fileCount(string $subdirectory = ''): int
    {
        $count = 0;

        foreach ($this->directories($subdirectory) as $directory) {
            $count += count($this->files->allFiles($directory));
        }

        return $count;
    }

    /**
     * Paths that sit next to an application root (routes/, tests/, database/).
     *
     * @return list<string>
     */
    public function siblings(string $relative): array
    {
        $relative = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
        $paths = [];

        $fallback = base_path($relative);

        if ($this->files->exists($fallback)) {
            $paths[] = realpath($fallback) ?: $fallback;
        }

        foreach ($this->namespaceRoots() as $root) {
            $candidate = dirname($root).DIRECTORY_SEPARATOR.$relative;

            if ($this->files->exists($candidate)) {
                $paths[] = realpath($candidate) ?: $candidate;
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * @return list<string>
     */
    private function namespaceRoots(): array
    {
        $namespace = trim($this->app->getNamespace(), '\\').'\\';
        $paths = [];

        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            foreach ($loader->getPrefixesPsr4() as $prefix => $directories) {
                foreach ($directories as $directory) {
                    $path = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $directory), DIRECTORY_SEPARATOR);
                    $resolved = realpath($path) ?: $path;
                    $normalized = str_replace('\\', '/', $resolved);

                    $matchesNamespace = $prefix === $namespace || str_starts_with($namespace, $prefix);
                    $looksLikeAppRoot = str_ends_with($normalized, '/app')
                        && ! str_contains($normalized, '/vendor/');

                    if ($matchesNamespace || $looksLikeAppRoot) {
                        $paths[] = $resolved;
                    }
                }
            }
        }

        return array_values(array_unique($paths));
    }
}
