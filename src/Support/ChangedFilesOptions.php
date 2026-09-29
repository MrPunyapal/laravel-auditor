<?php

declare(strict_types=1);

namespace LaravelAuditor\Support;

/**
 * Reads the `changed_files` configuration in one place.
 *
 * The `changed_files` collector and the `--dirty` report scope must agree on
 * what counts as an uncommitted change, so both resolve their options here
 * rather than each interpreting the config themselves.
 */
final class ChangedFilesOptions
{
    public function includeUntracked(): bool
    {
        return (bool) config('laravel-auditor.changed_files.include_untracked', true);
    }

    /**
     * Repository-relative path prefixes to exclude, normalized to `/` form.
     *
     * @return list<string>
     */
    public function ignore(): array
    {
        return array_values(array_filter(
            array_map(
                static fn (mixed $path): string => str_replace('\\', '/', trim((string) $path, " \t\n\r\0\x0B/")),
                (array) config('laravel-auditor.changed_files.ignore', []),
            ),
            static fn (string $path): bool => $path !== '',
        ));
    }

    public function maxFiles(): int
    {
        $max = (int) config('laravel-auditor.changed_files.max_files', 500);

        return $max > 0 ? $max : 500;
    }
}
