<?php

declare(strict_types=1);

namespace LaravelAuditor\Support;

use LaravelAuditor\Audit\Findings\FindingCollection;

/**
 * Resolves the file scope shared by `auditor:report` and `auditor:ci`.
 *
 * `--dirty` is the uncommitted working tree. `--base` is the committed range
 * from the merge base of a ref to HEAD, which is what a pull request actually
 * changed. Passing both unions the two sets. Both read the `changed_files`
 * configuration, so the scope and the collector agree on ignored paths and the
 * file cap. A truncated change set fails the command: gating on a partial list
 * would let a finding on an omitted file pass.
 */
final class DirtyScope
{
    public function __construct(
        private readonly GitStatus $git,
        private readonly ChangedFilesOptions $options,
    ) {}

    /**
     * Narrows findings to those touching the requested change set.
     *
     * @return array{ok: true, findings: FindingCollection, summary: string, meta: array<string, bool|int|string>}|array{ok: false, message: string, hint: string}
     */
    public function resolve(FindingCollection $findings, ?string $base, bool $dirty): array
    {
        if ($base === null && ! $dirty) {
            return [
                'ok' => true,
                'findings' => $findings,
                'summary' => '',
                'meta' => [],
            ];
        }

        $files = [];
        $max = $this->options->maxFiles();

        if ($base !== null) {
            $since = $this->git->changedSince($base, $this->options->ignore(), $max);

            if (! $since['available']) {
                return $this->rejected(
                    'Cannot resolve --base scope: '.(string) $since['reason'],
                    'Fetch the base ref and try again. In GitHub Actions, check out with fetch-depth: 0 so the ref (for example origin/main) exists locally. Drop --base to include every finding.',
                );
            }

            if ($since['truncated']) {
                return $this->tooLarge($max);
            }

            $files = $since['files'];
        }

        if ($dirty) {
            $changed = $this->git->changedFiles(
                includeUntracked: $this->options->includeUntracked(),
                ignore: $this->options->ignore(),
                max: $max,
            );

            if (! $changed['available']) {
                return $this->rejected(
                    'Cannot resolve --dirty scope: '.(string) $changed['reason'],
                    'Drop --dirty to include every finding, or run the command inside a git repository.',
                );
            }

            if ($changed['truncated']) {
                return $this->tooLarge($max);
            }

            $files = array_values(array_unique([...$files, ...$changed['files']]));
            sort($files);
        }

        if (count($files) > $max) {
            return $this->tooLarge($max);
        }

        $scoped = $findings->touching($files);
        $mode = $base !== null && $dirty ? 'base+dirty' : ($base !== null ? 'base' : 'dirty');

        $meta = [
            'scope' => $mode,
            'scope_file_count' => count($files),
            'scope_truncated' => false,
            'scope_total_findings' => $findings->count(),
        ];

        if ($base !== null) {
            $meta['scope_base'] = $base;
        }

        return [
            'ok' => true,
            'findings' => $scoped,
            'summary' => $this->summary($base, $dirty, count($files), $scoped->count(), $findings->count()),
            'meta' => $meta,
        ];
    }

    private function summary(?string $base, bool $dirty, int $files, int $scoped, int $total): string
    {
        $counts = sprintf('%d of %d finding(s) in scope.', $scoped, $total);

        if ($base !== null && $dirty) {
            return sprintf('Base scope (%s) plus uncommitted files: %d file(s), %s', $base, $files, $counts);
        }

        if ($base !== null) {
            return sprintf('Base scope (%s): %d changed file(s), %s', $base, $files, $counts);
        }

        return sprintf('Dirty scope: %d uncommitted file(s), %s', $files, $counts);
    }

    /**
     * @return array{ok: false, message: string, hint: string}
     */
    private function tooLarge(int $max): array
    {
        return $this->rejected(
            'Cannot resolve the change scope: the change set exceeds changed_files.max_files ('.$max.').',
            'Raise changed_files.max_files so every changed file is gated, or drop the scope flag to include every finding.',
        );
    }

    /**
     * @return array{ok: false, message: string, hint: string}
     */
    private function rejected(string $message, string $hint): array
    {
        return ['ok' => false, 'message' => $message, 'hint' => $hint];
    }
}
