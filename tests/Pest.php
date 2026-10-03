<?php

declare(strict_types=1);

use LaravelAuditor\Support\GitStatus;
use LaravelAuditor\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * Creates a throwaway git repository and returns its path.
 */
function changedFilesRepository(): string
{
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'laravel-auditor-git-'.uniqid();

    mkdir($path, 0777, true);

    foreach ([
        ['init', '-q'],
        ['config', 'user.email', 'auditor@example.test'],
        ['config', 'user.name', 'Auditor'],
        ['config', 'commit.gpgsign', 'false'],
    ] as $arguments) {
        runGit($path, ...$arguments);
    }

    return $path;
}

/**
 * Runs git inside the given directory and discards its output.
 */
function runGit(string $path, string ...$arguments): void
{
    $process = @proc_open(
        ['git', ...$arguments],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $path,
    );

    if (! is_resource($process)) {
        return;
    }

    foreach ($pipes as $pipe) {
        fclose($pipe);
    }

    proc_close($process);
}

function writeRepositoryFile(string $path, string $file, string $contents): void
{
    $target = $path.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $file);

    if (! is_dir(dirname($target))) {
        mkdir(dirname($target), 0777, true);
    }

    file_put_contents($target, $contents);
}

function removeRepository(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($items as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());

            continue;
        }

        // Git object files are read-only on Windows, which blocks plain unlink().
        @chmod($item->getPathname(), 0666);
        @unlink($item->getPathname());
    }

    @rmdir($path);
}

/**
 * Points the container's git reader at a specific directory.
 */
function useDirtyRepository(string $path): void
{
    app()->forgetInstance(GitStatus::class);
    app()->bind(GitStatus::class, static fn (): GitStatus => new GitStatus($path));
}
