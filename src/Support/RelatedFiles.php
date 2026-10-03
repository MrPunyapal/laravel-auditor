<?php

declare(strict_types=1);

namespace LaravelAuditor\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Finds the files a change set directly uses, without walking the rest of the application.
 *
 * One hop only: the classes a changed file imports, the view it renders, the
 * conventional Livewire view or class, and the test that mirrors it or names
 * its class. Callers elsewhere in the app are left alone, so a review of one
 * component does not become a review of every file that mentions it.
 */
final class RelatedFiles
{
    private const int MAX_SCANNED_FILES = 2000;

    private const int MAX_FILE_BYTES = 524288;

    /**
     * @param  list<string>  $changed  Application-relative paths.
     * @param  list<string>  $ignore  Repository-relative prefixes to exclude.
     * @return list<string>
     */
    public function for(string $root, array $changed, array $ignore = []): array
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $related = [];

        foreach ($changed as $file) {
            foreach ($this->candidates($root, $file) as $candidate) {
                if ($this->isIgnored($candidate, $ignore)) {
                    continue;
                }

                $related[$candidate] = true;
            }
        }

        foreach ($changed as $file) {
            unset($related[$file]);
        }

        $related = array_keys($related);
        sort($related);

        return $related;
    }

    /**
     * @return list<string>
     */
    private function candidates(string $root, string $file): array
    {
        $candidates = [];

        if (preg_match('#^app/Livewire/(.+)\.php$#', $file, $matches) === 1) {
            $segments = explode('/', $matches[1]);
            $kebab = array_map($this->kebab(...), $segments);
            $candidates[] = 'resources/views/livewire/'.implode('/', $kebab).'.blade.php';
            $candidates = [...$candidates, ...$this->filesContaining(
                $root,
                $root.'/resources/views',
                'livewire:'.implode('.', $kebab),
            )];
        }

        if (preg_match('#^resources/views/livewire/(.+)\.blade\.php$#', $file, $matches) === 1) {
            $segments = explode('/', $matches[1]);
            $studly = array_map($this->studly(...), $segments);
            $candidates[] = 'app/Livewire/'.implode('/', $studly).'.php';
        }

        if (preg_match('#^app/(.+)\.php$#', $file, $matches) === 1) {
            $candidates[] = 'tests/Feature/'.$matches[1].'Test.php';
            $candidates[] = 'tests/Unit/'.$matches[1].'Test.php';
        }

        $class = $this->classFor($root, $file);

        if ($class !== null) {
            $candidates = [...$candidates, ...$this->filesContaining($root, $root.'/tests', $class)];
        }

        $source = $this->source($root, $file);

        if ($source !== null) {
            foreach ($this->importedClasses($source) as $imported) {
                $path = $this->fileForClass($root, $imported);

                if ($path !== null) {
                    $candidates[] = $path;
                }
            }

            foreach ($this->views($source) as $view) {
                $candidates[] = $view;
            }
        }

        $existing = [];

        foreach ($candidates as $candidate) {
            $relative = $this->existing($root, $candidate);

            if ($relative !== null) {
                $existing[$relative] = true;
            }
        }

        return array_keys($existing);
    }

    /**
     * @return list<string>
     */
    private function importedClasses(string $source): array
    {
        $classes = [];

        if (preg_match_all('/^use\s+([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)\s*(?:as\s+[A-Za-z_][A-Za-z0-9_]*)?\s*;/m', $source, $uses) > 0) {
            foreach ($uses[1] as $class) {
                $classes[] = ltrim($class, '\\');
            }
        }

        if (preg_match_all('/^use\s+([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)\\\\\{([^}]+)\}\s*;/m', $source, $groups) > 0) {
            foreach ($groups[1] as $index => $prefix) {
                foreach (explode(',', $groups[2][$index]) as $part) {
                    $name = trim(preg_replace('/\s+as\s+.+$/', '', trim($part)) ?? '');

                    if ($name !== '') {
                        $classes[] = ltrim($prefix, '\\').'\\'.$name;
                    }
                }
            }
        }

        return $classes;
    }

    /**
     * @return list<string>
     */
    private function views(string $source): array
    {
        if (preg_match_all('/\bview\(\s*[\'"]([A-Za-z0-9_.\/-]+)[\'"]/', $source, $matches) === 0) {
            return [];
        }

        $views = [];

        foreach ($matches[1] as $name) {
            if (str_contains($name, '..')) {
                continue;
            }

            $views[] = 'resources/views/'.str_replace('.', '/', $name).'.blade.php';
        }

        return $views;
    }

    private function classFor(string $root, string $file): ?string
    {
        foreach ($this->prefixes($root) as $namespace => $directory) {
            if (str_starts_with($file, $directory) && str_ends_with($file, '.php')) {
                $relative = substr($file, strlen($directory), -4);

                return $namespace.str_replace('/', '\\', $relative);
            }
        }

        return null;
    }

    private function fileForClass(string $root, string $class): ?string
    {
        $class = ltrim($class, '\\');
        $match = null;

        foreach ($this->prefixes($root) as $namespace => $directory) {
            if (str_starts_with($class, $namespace) && ($match === null || strlen($namespace) > strlen($match[0]))) {
                $match = [$namespace, $directory];
            }
        }

        if ($match === null) {
            return null;
        }

        $relative = substr($class, strlen($match[0]));

        return $match[1].str_replace('\\', '/', $relative).'.php';
    }

    /**
     * @return array<string, string> Namespace prefix => relative directory, both with a trailing separator.
     */
    private function prefixes(string $root): array
    {
        $map = ['App\\' => 'app/'];
        $composer = $root.'/composer.json';

        if (! is_file($composer)) {
            return $map;
        }

        $json = json_decode((string) file_get_contents($composer), true);
        $psr4 = is_array($json) ? ($json['autoload']['psr-4'] ?? []) : [];

        if (! is_array($psr4)) {
            return $map;
        }

        $parsed = [];

        foreach ($psr4 as $namespace => $path) {
            if (! is_string($namespace) || ! is_string($path) || $namespace === '' || $path === '') {
                continue;
            }

            $parsed[trim(str_replace('/', '\\', $namespace), '\\').'\\'] = trim(str_replace('\\', '/', $path), '/').'/';
        }

        return $parsed === [] ? $map : $parsed;
    }

    /**
     * @return list<string>
     */
    private function filesContaining(string $root, string $directory, string $needle): array
    {
        if ($needle === '' || ! is_dir($directory)) {
            return [];
        }

        $found = [];
        $seen = 0;
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $name = $file->getFilename();

            if (! str_ends_with($name, '.php') && ! str_ends_with($name, '.blade.php')) {
                continue;
            }

            if (++$seen > self::MAX_SCANNED_FILES) {
                break;
            }

            if ($file->getSize() > self::MAX_FILE_BYTES) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            if ($contents !== false && str_contains($contents, $needle)) {
                $relative = $this->relative($root, $file->getPathname());

                if ($relative !== null) {
                    $found[] = $relative;
                }
            }
        }

        return $found;
    }

    private function source(string $root, string $file): ?string
    {
        if (! str_ends_with($file, '.php')) {
            return null;
        }

        $relative = $this->existing($root, $file);

        if ($relative === null) {
            return null;
        }

        $absolute = $root.'/'.$relative;
        $size = filesize($absolute);

        if ($size === false || $size > self::MAX_FILE_BYTES) {
            return null;
        }

        $contents = file_get_contents($absolute);

        return $contents === false ? null : $contents;
    }

    private function existing(string $root, string $relative): ?string
    {
        $relative = str_replace('\\', '/', $relative);

        if ($relative === '' || str_contains($relative, '..') || str_starts_with($relative, '/')) {
            return null;
        }

        $absolute = $root.'/'.$relative;

        if (! is_file($absolute)) {
            return null;
        }

        $realFile = realpath($absolute);
        $realRoot = realpath($root);

        if ($realFile === false || $realRoot === false) {
            return null;
        }

        $realFile = str_replace('\\', '/', $realFile);
        $realRoot = rtrim(str_replace('\\', '/', $realRoot), '/');

        return str_starts_with($realFile, $realRoot.'/') ? $relative : null;
    }

    private function relative(string $root, string $absolute): ?string
    {
        $absolute = str_replace('\\', '/', $absolute);
        $prefix = rtrim($root, '/').'/';

        if (! str_starts_with($absolute, $prefix)) {
            return null;
        }

        return substr($absolute, strlen($prefix));
    }

    private function kebab(string $segment): string
    {
        $kebab = preg_replace('/(?<!^)[A-Z]/', '-$0', $segment) ?? $segment;

        return strtolower($kebab);
    }

    private function studly(string $segment): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $segment)));
    }

    /**
     * @param  list<string>  $ignore
     */
    private function isIgnored(string $path, array $ignore): bool
    {
        foreach ($ignore as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }
}
