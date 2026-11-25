<?php

declare(strict_types=1);

namespace LaravelAuditor\Context\Reviews;

use LaravelAuditor\Audit\Findings\FindingBaseline;
use LaravelAuditor\Audit\Findings\FindingCollection;
use LaravelAuditor\Context\Migrations\MigrationDriverAdvisor;
use LaravelAuditor\Context\Migrations\MigrationRiskAnalyzer;
use LaravelAuditor\Context\Migrations\MigrationSourceReview;
use LaravelAuditor\Context\Snapshots\SnapshotComparator;
use RuntimeException;

/**
 * Combines existing read-only evidence without inferring vulnerabilities.
 */
final class ChangeReviewBuilder
{
    public function __construct(
        private readonly SnapshotComparator $snapshots,
        private readonly MigrationRiskAnalyzer $migrations,
    ) {}

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  list<string>  $migrationFiles
     * @return array<string, mixed>
     */
    public function review(array $before, array $after, ?FindingCollection $findings = null, ?FindingBaseline $baseline = null, array $migrationFiles = [], bool $driverGuidance = false): array
    {
        if ($baseline !== null && $findings === null) {
            throw new RuntimeException('A findings baseline requires a complete current findings file.');
        }

        $comparison = $this->snapshots->compare($before, $after);
        $schemaIncluded = array_key_exists('schema', $before['sections']);
        $migrationRisk = $schemaIncluded ? $this->migrations->analyze($before, $after) : null;
        if (($migrationFiles !== [] || $driverGuidance) && $migrationRisk === null) {
            throw new RuntimeException('Migration source review and driver guidance require schema-enabled snapshots on both sides.');
        }

        if ($migrationRisk !== null && $migrationFiles !== []) {
            $migrationRisk['source_review'] = app(MigrationSourceReview::class)->review($migrationFiles);
            $migrationRisk['coverage'] = 'snapshot_structure_plus_selected_php_source';
            $migrationRisk['limitations'][0] = 'Only recognized patterns in explicitly selected migration source files were inspected. Other migration code was not analyzed.';
        }

        if ($migrationRisk !== null && $driverGuidance) {
            $migrationRisk['driver_review'] = app(MigrationDriverAdvisor::class)->review($migrationRisk);
        }

        $baselineComparison = $baseline?->compare($findings);

        return [
            'format' => 'laravel-auditor-change-review',
            'version' => 1,
            'scope' => 'explicit_saved_snapshots',
            'coverage' => [
                'routes_and_authorization' => true,
                'database_schema' => $schemaIncluded,
                'migration_source' => $migrationFiles !== [],
                'driver_guidance' => $driverGuidance,
                'findings_supplied' => $findings !== null,
                'baseline_compared' => $baseline !== null,
            ],
            'summary' => [
                'structural_changes_present' => $comparison['has_changes'],
                'migration_risk_indicators' => $migrationRisk === null ? null : count($migrationRisk['indicators']),
                'findings_count' => $findings?->count(),
                'baseline' => $baselineComparison?->summary(),
            ],
            'results' => [
                'snapshot_comparison' => $comparison,
                'migration_risk' => $migrationRisk,
                'finding_review' => $findings === null ? null : [
                    'findings' => $findings->toArray(),
                    'baseline' => $baselineComparison?->toArray(),
                ],
            ],
            'limitations' => [
                'Only explicitly provided artifacts and selected migration source files were reviewed; this command does not collect live application state or run an AI agent.',
                'Snapshot differences and migration indicators are structural evidence, not confirmed security vulnerabilities or deployment-safety assessments.',
                $migrationFiles === []
                    ? 'Migration PHP was not inspected or executed. Database schema coverage requires complete schema-enabled snapshots on both sides.'
                    : 'Selected migration PHP was tokenized, not executed; source indicators are separate from structural schema indicators.',
                'Finding confidence is provided by the finding producer, not independently verified by this command.',
                'Baseline comparison requires findings from equivalent complete audit scopes; an absent finding does not prove it was fixed.',
            ],
        ];
    }
}
