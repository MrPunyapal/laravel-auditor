<?php

declare(strict_types=1);

namespace LaravelAuditor\Context\Reviews;

use LaravelAuditor\Context\Snapshots\SnapshotProvenance;
use LaravelAuditor\Support\ChangedFilesOptions;
use LaravelAuditor\Support\GitStatus;
use RuntimeException;

/**
 * Associates trusted saved artifacts with a local committed Git range.
 * Never checks out or boots another revision.
 */
final class GitArtifactReview
{
    public function __construct(
        private readonly GitStatus $git,
        private readonly ChangedFilesOptions $options,
    ) {}

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, mixed>
     */
    public function review(string $base, array $before, array $after): array
    {
        $head = $this->git->revision();
        $mergeBase = $head === null ? null : $this->git->mergeBase($base, $head);

        if ($head === null || $mergeBase === null) {
            throw new RuntimeException('Cannot resolve a unique Git merge base and HEAD. Fetch the requested base and full history.');
        }

        $status = $this->git->changedFiles(max: 0);

        if (! $status['available'] || $status['truncated']) {
            throw new RuntimeException('Git-aware review requires a clean working tree with available Git status.');
        }

        $old = SnapshotProvenance::read($before);
        $new = SnapshotProvenance::read($after);

        if ($old === null || $new === null || $old['dirty'] !== false || $new['dirty'] !== false
            || $old['revision'] !== $mergeBase || $new['revision'] !== $head) {
            throw new RuntimeException('Supply clean provenance-enabled snapshots matching the merge base and HEAD.');
        }

        $changed = $this->git->changedSince($mergeBase, $this->options->ignore(), $this->options->maxFiles());

        if (! $changed['available'] || $changed['truncated'] || $this->git->revision() !== $head) {
            throw new RuntimeException('Cannot resolve a complete stable Git change scope; check changed_files.max_files and Git history.');
        }

        return [
            'merge_base' => $mergeBase,
            'head' => $head,
            'changed_files' => $changed['files'],
            'ignored_prefixes' => $this->options->ignore(),
            'file_count' => count($changed['files']),
            'artifact_revisions_match' => true,
            'artifact_authenticity_verified' => false,
            'limitations' => [
                'Revision metadata matches local Git history but is unsigned; trust the artifact capture and storage process.',
                'The change list is application-relative and respects configured ignore prefixes. Findings are not filtered by this list.',
                'Ignored files, dependency installations, runtime configuration and database state are not authenticated by Git.',
            ],
        ];
    }
}
