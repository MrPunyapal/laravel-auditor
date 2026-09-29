<?php

declare(strict_types=1);

namespace LaravelAuditor\Support;

use Symfony\Component\Process\Process;
use Throwable;

/**
 * Reads the uncommitted working-tree state of the application's git repository.
 *
 * Everything here is read-only. Git is an optional, host-provided tool: when the
 * binary, the repository, or the process functions are missing the reader fails
 * soft with a reason instead of throwing, so an audit can continue with a full
 * scope rather than crash.
 */
final class GitStatus
{
    /**
     * @param  string  $basePath  The application root to inspect.
     * @param  int  $timeout  Seconds before the git call is abandoned.
     */
    public function __construct(
        private readonly string $basePath,
        private readonly int $timeout = 10,
    ) {}

    /**
     * Uncommitted files, relative to the application root.
     *
     * Staged, unstaged, and untracked paths are included. Deleted paths are kept
     * because a deleted file is still a change an audit should look at.
     *
     * @param  list<string>  $ignore  Path prefixes to exclude.
     * @return array{available: bool, reason: string|null, files: list<string>, truncated: bool}
     */
    public function changedFiles(bool $includeUntracked = true, array $ignore = [], int $max = 500): array
    {
        $prefix = $this->prefix();

        if (isset($prefix['reason'])) {
            return ['available' => false, 'reason' => $prefix['reason'], 'files' => [], 'truncated' => false];
        }

        $result = $this->run([
            'status',
            '--porcelain=v1',
            '-z',
            '--untracked-files='.($includeUntracked ? 'all' : 'no'),
            '--ignored=no',
        ]);

        if (! $result['ok']) {
            return ['available' => false, 'reason' => $result['reason'], 'files' => [], 'truncated' => false];
        }

        $files = self::parsePorcelain($result['output'], $prefix['prefix'], $ignore);

        $truncated = count($files) > $max;

        return [
            'available' => true,
            'reason' => null,
            'files' => $truncated ? array_slice($files, 0, $max) : $files,
            'truncated' => $truncated,
        ];
    }

    /**
     * Parses `git status --porcelain=v1 -z` output into application-relative paths.
     *
     * With `-z` git emits NUL-separated fields and never quotes paths, so unusual
     * filenames survive intact. Rename and copy entries carry the original path
     * in an extra field, which is consumed and dropped.
     *
     * @param  list<string>  $ignore
     * @return list<string>
     */
    public static function parsePorcelain(string $output, string $prefix = '', array $ignore = []): array
    {
        $fields = explode("\0", $output);
        $paths = [];

        for ($index = 0; $index < count($fields); $index++) {
            $field = $fields[$index];

            if (strlen($field) < 4) {
                continue;
            }

            $status = substr($field, 0, 2);
            $path = substr($field, 3);

            // A rename or copy is followed by the original path in its own field.
            if (str_contains($status, 'R') || str_contains($status, 'C')) {
                $index++;
            }

            $relative = self::relative($path, $prefix);

            if ($relative === null || self::isIgnored($relative, $ignore)) {
                continue;
            }

            $paths[$relative] = true;
        }

        $paths = array_keys($paths);

        sort($paths);

        return $paths;
    }

    /**
     * Whether a repository-relative path starts with any ignored prefix.
     *
     * A prefix only matches whole path segments, so `app/Storage` is never
     * excluded by an `app` entry.
     *
     * @param  list<string>  $ignore
     */
    public static function isIgnored(string $path, array $ignore): bool
    {
        foreach ($ignore as $prefix) {
            $prefix = trim(str_replace('\\', '/', $prefix), '/');

            if ($prefix === '') {
                continue;
            }

            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Strips the repository prefix so paths are relative to the application root.
     *
     * Paths outside the application root — a monorepo sibling, for example — are
     * rejected instead of rewritten into a `../` reference.
     */
    private static function relative(string $path, string $prefix): ?string
    {
        $normalized = str_replace('\\', '/', $path);

        if ($prefix !== '') {
            if ($normalized === $prefix) {
                return null;
            }

            if (! str_starts_with($normalized, $prefix)) {
                return null;
            }

            $normalized = substr($normalized, strlen($prefix));
        }

        $normalized = ltrim($normalized, '/');

        if ($normalized === '' || str_contains($normalized, '../')) {
            return null;
        }

        return $normalized;
    }

    /**
     * The path of the application root relative to the repository root.
     *
     * @return array{prefix: string}|array{reason: string}
     */
    private function prefix(): array
    {
        $result = $this->run(['rev-parse', '--show-prefix']);

        if (! $result['ok']) {
            return ['reason' => $result['reason']];
        }

        $prefix = str_replace('\\', '/', trim($result['output']));

        return ['prefix' => $prefix === '' ? '' : rtrim($prefix, '/').'/'];
    }

    /**
     * @param  list<string>  $arguments
     * @return array{ok: true, output: string}|array{ok: false, reason: string}
     */
    private function run(array $arguments): array
    {
        if (! class_exists(Process::class)) {
            return ['ok' => false, 'reason' => 'symfony/process is not installed'];
        }

        $process = new Process(['git', ...$arguments], $this->basePath);

        try {
            $process->setTimeout($this->timeout);
            $process->run();
        } catch (Throwable $e) {
            return ['ok' => false, 'reason' => 'git could not run: '.$e->getMessage()];
        }

        if (! $process->isSuccessful()) {
            $stderr = trim(preg_replace('/\s+/', ' ', $process->getErrorOutput()) ?? $process->getErrorOutput());

            return [
                'ok' => false,
                'reason' => $stderr === '' ? 'git exited with code '.$process->getExitCode() : $stderr,
            ];
        }

        return ['ok' => true, 'output' => $process->getOutput()];
    }
}
