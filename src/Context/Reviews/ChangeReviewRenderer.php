<?php

declare(strict_types=1);

namespace LaravelAuditor\Context\Reviews;

use RuntimeException;

/**
 * Presents existing review evidence without promoting observations to findings.
 */
final class ChangeReviewRenderer
{
    /**
     * @param  array<string, mixed>  $review
     */
    public static function render(array $review, string $format): string
    {
        if ($format === 'json') {
            return json_encode($review, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
        }

        if (! in_array($format, ['markdown', 'text'], true)) {
            throw new RuntimeException('Unknown review format. Use json, markdown, or text.');
        }

        $markdown = $format === 'markdown';
        $summary = $review['summary'];
        $results = $review['results'];
        $comparison = $results['snapshot_comparison'];
        $changes = $comparison['changes'];
        $lines = [
            ($markdown ? '# ' : '').'Laravel Auditor Change Review',
            '',
            'Structural evidence only. Deployment safety has not been verified.',
            'Structural changes present: '.($summary['structural_changes_present'] ? 'yes' : 'no'),
            '',
            self::heading('Coverage', $markdown),
        ];

        foreach ($review['coverage'] as $section => $covered) {
            $lines[] = '- '.str_replace('_', ' ', (string) $section).': '.($covered ? 'included' : 'not included');
        }

        $lines[] = '';
        $lines[] = self::heading('Routes and authorization', $markdown);

        foreach (['added', 'removed', 'modified'] as $kind) {
            $lines[] = ucfirst($kind).' routes: '.count($changes['routes'][$kind]);

            foreach ($changes['routes'][$kind] as $route) {
                $identity = $kind === 'modified' ? $route['identity'] : $route;
                $lines[] = '- '.self::value(implode(', ', $identity['methods']), $markdown).' '.self::value($identity['uri'], $markdown)
                    .' (domain: '.self::value($identity['domain'], $markdown).')';

                if ($kind === 'modified') {
                    foreach (['name', 'action', 'middleware', 'resolved_middleware'] as $field) {
                        if ($route['before'][$field] !== $route['after'][$field]) {
                            $lines[] = '  - '.str_replace('_', ' ', $field).': '.self::value($route['before'][$field], $markdown)
                                .' → '.self::value($route['after'][$field], $markdown);
                        }
                    }
                } else {
                    $lines[] = '  - Action: '.self::value($route['action'], $markdown);
                    $lines[] = '  - Resolved middleware: '.self::value($route['resolved_middleware'], $markdown);
                }
            }
        }

        $lines[] = 'Route registration order changed: '.($changes['route_order']['changed'] ? 'yes' : 'no');

        if ($changes['route_order']['changed']) {
            $lines[] = '- Before: '.self::value($changes['route_order']['before'], $markdown);
            $lines[] = '- After: '.self::value($changes['route_order']['after'], $markdown);
        }

        foreach ($changes['authorization'] as $section => $diff) {
            foreach ($diff as $kind => $entries) {
                foreach ($entries as $key => $entry) {
                    $lines[] = '- '.str_replace('_', ' ', (string) $section).' '.$kind.': '
                        .(is_string($key) ? self::value($key, $markdown).' — ' : '').self::value($entry, $markdown);
                }
            }
        }

        $lines[] = '';
        $lines[] = self::heading('Database schema and migration risk', $markdown);

        if ($results['migration_risk'] === null) {
            $lines[] = 'Not reviewed: schema-enabled snapshots were not supplied.';
        } else {
            $risk = $results['migration_risk'];
            $lines[] = 'Driver: '.self::value($risk['driver'], $markdown);
            foreach (['added', 'removed', 'modified'] as $kind) {
                $lines[] = ucfirst($kind).' tables: '.count($changes['schema'][$kind]);

                foreach ($changes['schema'][$kind] as $table) {
                    $identity = $kind === 'modified' ? $table['after'] : $table;
                    $lines[] = '- '.self::value($identity['schema'] === null ? $identity['name'] : $identity['schema'].'.'.$identity['name'], $markdown);
                }
            }

            $lines[] = 'Potential migration risk indicators: '.count($risk['indicators']);

            // Preserve deterministic order within each level, with high-priority observations first.
            foreach (['high', 'review'] as $level) {
                foreach ($risk['indicators'] as $indicator) {
                    if ($indicator['level'] !== $level) {
                        continue;
                    }

                    $location = array_filter([$indicator['schema'], $indicator['table'], $indicator['column']], static fn (mixed $part): bool => $part !== null);
                    $lines[] = '- '.strtoupper($level).' '.self::value(implode('.', $location), $markdown).': '.self::value($indicator['code'], $markdown)
                        .' — '.self::value($indicator['message'], $markdown);
                    $lines[] = '  - Verify: '.self::value($indicator['verify'], $markdown);

                    if (isset($indicator['constraint'])) {
                        $lines[] = '  - Constraint: '.self::value($indicator['constraint'], $markdown);
                    }
                }
            }

            if (isset($risk['source_review'])) {
                $lines[] = self::heading('Selected migration source (not executed)', $markdown);

                foreach ($risk['source_review']['files'] as $file) {
                    $lines[] = '- '.self::value($file['file'], $markdown).' (coverage: '.self::value($file['coverage'], $markdown).')';

                    foreach ($file['indicators'] as $indicator) {
                        $lines[] = '  - '.self::value($indicator, $markdown);
                    }
                }

                foreach ($risk['source_review']['limitations'] as $limitation) {
                    $lines[] = '- '.self::value($limitation, $markdown);
                }
            }

            if (isset($risk['driver_review'])) {
                $lines[] = self::heading('Conditional driver guidance', $markdown);

                foreach ($risk['driver_review']['questions'] as $question) {
                    $lines[] = '- '.self::value($question, $markdown);
                }

                foreach ($risk['driver_review']['limitations'] as $limitation) {
                    $lines[] = '- '.self::value($limitation, $markdown);
                }
            }

            foreach ($risk['limitations'] as $limitation) {
                $lines[] = '- '.self::value($limitation, $markdown);
            }
        }

        $lines[] = '';
        $lines[] = self::heading('Supplied findings (not independently verified)', $markdown);

        if ($results['finding_review'] === null) {
            $lines[] = 'Not reviewed: no findings file was supplied.';
        } else {
            $findingReview = $results['finding_review'];
            $baselineStates = [];
            $lines[] = 'Findings supplied: '.$summary['findings_count'];

            if ($summary['baseline'] !== null) {
                $baseline = $summary['baseline'];
                $lines[] = sprintf('Baseline: %d new, %d persistent, %d absent from the current findings file.', $baseline['new'], $baseline['persistent'], $baseline['absent']);

                $baselineStates = array_column($findingReview['baseline']['states'] ?? [], 'state', 'id');
                foreach ($findingReview['baseline']['regressions'] ?? [] as $regression) {
                    $changes = [];

                    if ($regression['severity_increased']) {
                        $changes[] = 'severity increased from '.self::value($regression['previous_severity'], $markdown)
                            .' to '.self::value($regression['current_severity'], $markdown);
                    }

                    if ($regression['reopened']) {
                        $changes[] = 'reopened from '.self::value($regression['previous_status'], $markdown);
                    }
                    $lines[] = '- Regression: '.self::value($regression['id'], $markdown).' ('.implode('; ', $changes).')';
                }

                foreach ($findingReview['baseline']['absent'] as $absent) {
                    $lines[] = '- Absent: '.self::value($absent['id'], $markdown).' — '.self::value($absent['title'], $markdown);
                }

                foreach ($findingReview['baseline']['notes'] as $note) {
                    $lines[] = '- '.self::value($note, $markdown);
                }
            } else {
                $lines[] = 'Historical baseline not supplied.';
            }

            $newIds = $findingReview['baseline'] === null ? [] : array_column($findingReview['baseline']['new'], 'id');

            foreach ($findingReview['findings'] as $finding) {
                $state = $baselineStates[$finding['id']] ?? (in_array($finding['id'], $newIds, true) ? 'new' : 'persistent');
                $state = $findingReview['baseline'] === null ? '' : '['.strtoupper($state).'] ';
                $lines[] = '- '.$state.self::value($finding['id'], $markdown).' ['.self::value($finding['severity'], $markdown).'] '
                    .self::value($finding['title'], $markdown).' (status: '.self::value($finding['status'], $markdown)
                    .', confidence: '.self::value($finding['confidence'], $markdown).')';

                foreach ($finding['evidence'] as $evidence) {
                    $lines[] = '  - Evidence: '.self::value($evidence, $markdown);
                }
            }
        }

        $lines[] = '';
        $lines[] = self::heading('Limitations', $markdown);

        foreach ($review['limitations'] as $limitation) {
            $lines[] = '- '.self::value($limitation, $markdown);
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    private static function heading(string $title, bool $markdown): string
    {
        return ($markdown ? '## ' : '').$title;
    }

    /**
     * Encode untrusted metadata on a single line, including control and Unicode
     * formatting characters. Prevent Markdown code-span termination.
     */
    private static function value(mixed $value, bool $markdown): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        if (is_string($value)) {
            $encoded = substr($encoded, 1, -1);
        }

        if ($encoded === '') {
            $encoded = '""';
        }

        $encoded = str_replace([chr(127), '##['], ['\u007f', '\u0023#['], $encoded);

        return $markdown ? chr(96).str_replace(chr(96), '\u0060', $encoded).chr(96) : $encoded;
    }
}
