<?php

declare(strict_types=1);

namespace LaravelAuditor\Support;

/**
 * The file set an agent should read for the work in progress.
 *
 * `changed` is the uncommitted working tree, using the same options as the
 * `changed_files` collector. `related` is the view, test, or class those files
 * directly use. `scope` is the union, and it is the audit boundary.
 */
final class ReviewScope
{
    public function __construct(
        private readonly GitStatus $git,
        private readonly ChangedFilesOptions $options,
        private readonly string $root,
    ) {}

    /**
     * @return array{available: bool, reason: string|null, mode: string, changed: list<string>, related: list<string>, scope: list<string>, changed_count: int, related_count: int, truncated: bool}
     */
    public function collect(): array
    {
        $changed = $this->git->changedFiles(
            includeUntracked: $this->options->includeUntracked(),
            ignore: $this->options->ignore(),
            max: $this->options->maxFiles(),
        );

        if (! $changed['available']) {
            return $this->unavailable((string) $changed['reason']);
        }

        $related = (new RelatedFiles)->for($this->root, $changed['files'], $this->options->ignore());
        $truncated = $changed['truncated'];
        $room = max(0, $this->options->maxFiles() - count($changed['files']));

        if (count($related) > $room) {
            $related = array_slice($related, 0, $room);
            $truncated = true;
        }

        return [
            'available' => true,
            'reason' => null,
            'mode' => 'dirty',
            'changed' => $changed['files'],
            'related' => $related,
            'scope' => [...$changed['files'], ...$related],
            'changed_count' => count($changed['files']),
            'related_count' => count($related),
            'truncated' => $truncated,
        ];
    }

    /**
     * @return array{available: bool, reason: string|null, mode: string, changed: list<string>, related: list<string>, scope: list<string>, changed_count: int, related_count: int, truncated: bool}
     */
    private function unavailable(string $reason): array
    {
        return [
            'available' => false,
            'reason' => $reason,
            'mode' => 'dirty',
            'changed' => [],
            'related' => [],
            'scope' => [],
            'changed_count' => 0,
            'related_count' => 0,
            'truncated' => false,
        ];
    }
}
