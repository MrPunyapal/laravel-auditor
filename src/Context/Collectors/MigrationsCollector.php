<?php

declare(strict_types=1);

namespace LaravelAuditor\Context\Collectors;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Filesystem\Filesystem;
use LaravelAuditor\Context\ContextCollector;
use LaravelAuditor\Support\ApplicationPaths;
use Throwable;

/**
 * Lists the application's migration files.
 */
final class MigrationsCollector implements ContextCollector
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly ApplicationPaths $paths,
        private readonly ?Migrator $migrator = null,
    ) {}

    public function name(): string
    {
        return 'migrations';
    }

    public function description(): string
    {
        return 'List the application migration files and their purpose.';
    }

    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $files = array_keys($this->registeredFiles());
        sort($files, SORT_STRING);

        return [
            'count' => count($files),
            'migrations' => array_map($this->inspect(...), $files),
        ];
    }

    /**
     * @return array<string, string> Display paths mapped to validated canonical paths.
     */
    public function registeredFiles(): array
    {
        $migrations = [];

        foreach ($this->directories() as $directory) {
            foreach ($this->files->allFiles($directory) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $resolved = $file->getRealPath();

                if ($resolved === false || ! str_starts_with($resolved, rtrim($directory, '/\\').DIRECTORY_SEPARATOR)) {
                    continue;
                }

                $migrations[$this->paths->relativeToBase($file->getPathname())] = $resolved;
            }
        }

        return $migrations;
    }

    /**
     * @return list<string>
     */
    private function directories(): array
    {
        $candidates = [
            database_path('migrations'),
            ...$this->paths->siblings('database/migrations'),
        ];

        try {
            $migrator = $this->migrator ?? app('migrator');

            foreach ($migrator->paths() as $path) {
                if ($path !== '') {
                    $candidates[] = $path;
                }
            }
        } catch (Throwable) {
        }

        $directories = [];

        foreach ($candidates as $candidate) {
            if (! $this->files->isDirectory($candidate)) {
                continue;
            }

            $resolved = realpath($candidate) ?: $candidate;
            $directories[$resolved] = $resolved;
        }

        return array_values($directories);
    }

    /**
     * @return array<string, mixed>
     */
    private function inspect(string $relative): array
    {
        return [
            'file' => $relative,
            'name' => $this->nameFromFile($relative),
            'batch' => $this->batchFromFile($relative),
        ];
    }

    private function nameFromFile(string $file): string
    {
        return preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', pathinfo($file, PATHINFO_FILENAME)) ?? '';
    }

    private function batchFromFile(string $file): ?string
    {
        if (preg_match('/^(\d{4}_\d{2}_\d{2}_\d{6})_/', pathinfo($file, PATHINFO_FILENAME), $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
