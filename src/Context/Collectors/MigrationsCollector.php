<?php

declare(strict_types=1);

namespace LaravelAuditor\Context\Collectors;

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
        $migrations = [];

        foreach ($this->directories() as $directory) {
            foreach ($this->files->allFiles($directory) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $migrations[] = $this->inspect($file->getPathname());
            }
        }

        $unique = [];

        foreach ($migrations as $migration) {
            $unique[$migration['file']] = $migration;
        }

        $migrations = array_values($unique);

        usort($migrations, static fn (array $a, array $b): int => strcmp((string) $a['file'], (string) $b['file']));

        return [
            'count' => count($migrations),
            'migrations' => $migrations,
        ];
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
            foreach (app('migrator')->paths() as $path) {
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
    private function inspect(string $path): array
    {
        $relative = $this->relative($path);

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

    private function relative(string $path): string
    {
        $base = str_replace('\\', '/', rtrim(base_path(), '/\\'));
        $path = str_replace('\\', '/', $path);

        if ($path === $base) {
            return '.';
        }

        if (str_starts_with($path, $base.'/')) {
            return substr($path, strlen($base) + 1);
        }

        return $path;
    }
}
