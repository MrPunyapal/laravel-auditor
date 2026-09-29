<?php

declare(strict_types=1);

namespace LaravelAuditor\Support;

use LaravelAuditor\Audit\Findings\FindingCollection;

/**
 * Resolves the `--dirty` scope shared by `auditor:report` and `auditor:ci`.
 *
 * The scope is the uncommitted working tree, resolved through the same
 * `changed_files` configuration the collector uses, so a report and the
 * collector can never disagree about what "dirty" means.
 */
final class DirtyScope
{
    public function __construct(
        private readonly GitStatus $git,
        private readonly ChangedFilesOptions $options,
    ) {}

    /**
     * Narrows findings to those touching uncommitted files.
     *
     * @return array{ok: true, findings: FindingCollection, file_count: int, truncated: bool}|array{ok: false, reason: string}
     */
    public function apply(FindingCollection $findings): array
    {
        $result = $this->git->changedFiles(
            includeUntracked: $this->options->includeUntracked(),
            ignore: $this->options->ignore(),
            max: $this->options->maxFiles(),
        );

        if (! $result['available']) {
            return ['ok' => false, 'reason' => (string) $result['reason']];
        }

        return [
            'ok' => true,
            'findings' => $findings->touching($result['files']),
            'file_count' => count($result['files']),
            'truncated' => $result['truncated'],
        ];
    }
}
