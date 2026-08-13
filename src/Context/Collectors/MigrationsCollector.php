<?php

declare(strict_types=1);

namespace LaravelAuditor\Context\Collectors;

use Illuminate\Filesystem\Filesystem;
use LaravelAuditor\Context\ContextCollector;

/**
 * Lists the application's migration files.
 */
final class MigrationsCollector implements ContextCollector
{
    public function __construct(
        private readonly Filesystem $files,
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

        $directories = array_filter([
            database_path('migrations'),
        ], fn (string $path): bool => $this->files->isDirectory($path));

        foreach ($directories as $directory) {
            foreach ($this->files->allFiles($directory) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $migrations[] = $this->inspect($file->getPathname());
            }
        }

        usort($migrations, static fn (array $a, array $b): int => strcmp((string) $a['file'], (string) $b['file']));

        return [
            'count' => count($migrations),
            'migrations' => $migrations,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function inspect(string $path): array
    {
        $relative = ltrim(str_replace('\\', '/', substr($path, strlen(database_path()))), '/');

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
