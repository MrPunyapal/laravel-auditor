<?php

declare(strict_types=1);

namespace LaravelAuditor\Context\Collectors;

use LaravelAuditor\Context\ContextCollector;
use LaravelAuditor\Support\GitStatus;

/**
 * Lists the uncommitted files in the application's working tree.
 *
 * This is a scope signal, not an audit result: it lets an agent narrow a review
 * to the code currently in flight instead of re-reading the whole application.
 * Git is optional, so the collector reports a reason instead of failing when the
 * repository or binary is unavailable.
 */
final class ChangedFilesCollector implements ContextCollector
{
    public function __construct(
        private readonly GitStatus $git,
    ) {}

    public function name(): string
    {
        return 'changed_files';
    }

    public function description(): string
    {
        return 'List uncommitted files (staged, unstaged, untracked) so a review can be scoped to the work in progress. Read-only; returns available=false with a reason when git is unavailable.';
    }

    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $result = $this->git->changedFiles(
            includeUntracked: (bool) config('laravel-auditor.changed_files.include_untracked', true),
            ignore: $this->ignore(),
            max: $this->max(),
        );

        if (! $result['available']) {
            return [
                'available' => false,
                'reason' => $result['reason'],
                'count' => 0,
                'files' => [],
            ];
        }

        return [
            'available' => true,
            'count' => count($result['files']),
            'truncated' => $result['truncated'],
            'files' => $result['files'],
        ];
    }

    /**
     * @return list<string>
     */
    private function ignore(): array
    {
        return array_values(array_filter(
            array_map(
                static fn (mixed $path): string => str_replace('\\', '/', trim((string) $path, " \t\n\r\0\x0B/")),
                (array) config('laravel-auditor.changed_files.ignore', []),
            ),
            static fn (string $path): bool => $path !== '',
        ));
    }

    private function max(): int
    {
        $max = (int) config('laravel-auditor.changed_files.max_files', 500);

        return $max > 0 ? $max : 500;
    }
}
