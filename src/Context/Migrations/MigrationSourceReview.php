<?php

declare(strict_types=1);

namespace LaravelAuditor\Context\Migrations;

use LaravelAuditor\Context\Collectors\MigrationsCollector;
use RuntimeException;

/**
 * Reviews only explicitly selected, registered migration PHP files.
 */
final class MigrationSourceReview
{
    public function __construct(
        private readonly MigrationsCollector $migrations,
        private readonly MigrationSourceInspector $inspector,
    ) {}

    /**
     * @param  list<string>  $requested
     * @return array<string, mixed>
     */
    public function review(array $requested): array
    {
        if ($requested === [] || count($requested) > 50) {
            throw new RuntimeException('Select between one and 50 migration files.');
        }

        $registered = array_flip($this->migrations->registeredFiles());

        $results = [];
        $seen = [];

        foreach ($requested as $request) {
            if ($request === '' || ! str_ends_with(strtolower($request), '.php')) {
                throw new RuntimeException('Migration inputs must be registered .php files.');
            }

            $real = realpath($this->path($request));

            if ($real === false || ! isset($registered[$real]) || ! is_file($real) || ! is_readable($real)) {
                throw new RuntimeException('The selected migration file was not found in the registered migration paths.');
            }

            if (isset($seen[$real])) {
                continue;
            }

            $seen[$real] = true;
            $size = filesize($real);

            if ($size === false || $size > 1048576) {
                throw new RuntimeException('A migration file exceeds the 1 MiB review limit or cannot be measured.');
            }

            $source = file_get_contents($real, false, null, 0, 1048577);

            if (! is_string($source) || strlen($source) > 1048576) {
                throw new RuntimeException('The selected migration file could not be read.');
            }

            $review = $this->inspector->inspect($source);

            if ($review['up_found'] !== true) {
                throw new RuntimeException('Cannot inspect up() in selected migration: '.$registered[$real]);
            }

            $results[] = [
                'file' => $registered[$real],
                'coverage' => $review['coverage'],
                'indicators' => $review['indicators'],
            ];
        }

        usort($results, static fn (array $a, array $b): int => strcmp($a['file'], $b['file']));

        return [
            'coverage' => 'explicit_migration_source_subset',
            'files' => $results,
            'limitations' => [
                'Only explicitly selected registered migration files were tokenized. Other migrations and custom helpers were not assessed.',
                'Static source occurrences may be conditional or never run. Raw SQL contents and dynamic expressions were not interpreted.',
                'No source indicator proves that production data or deployment is safe.',
            ],
        ];
    }

    private function path(string $name): string
    {
        if (str_starts_with($name, '/') || preg_match('/^[a-zA-Z]:[\\\\\\/]/', $name) === 1
            || (DIRECTORY_SEPARATOR === '\\' && str_starts_with($name, '\\\\'))) {
            return $name;
        }

        return base_path($name);
    }
}
