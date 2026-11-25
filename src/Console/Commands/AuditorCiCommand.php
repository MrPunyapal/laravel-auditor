<?php

declare(strict_types=1);

namespace LaravelAuditor\Console\Commands;

use Illuminate\Console\Command;
use LaravelAuditor\Audit\Enums\FindingStatus;
use LaravelAuditor\Audit\Enums\Severity;
use LaravelAuditor\Audit\Findings\Finding;
use LaravelAuditor\Audit\Findings\FindingBaseline;
use LaravelAuditor\Audit\Findings\FindingCollection;
use LaravelAuditor\Audit\Findings\FindingLoader;
use LaravelAuditor\Audit\Reports\AuditReport;
use LaravelAuditor\Audit\Reports\ReportRendererFactory;
use LaravelAuditor\Context\ProjectContext;
use LaravelAuditor\Support\DirtyScope;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use ValueError;

/**
 * Fails CI when open findings meet or exceed a severity threshold.
 */
class AuditorCiCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'auditor:ci
        {--findings= : Path to a JSON file containing findings}
        {--fail-on=high : Minimum severity that fails CI (critical, high, medium, low, info)}
        {--format=text : Output format (text, json, sarif, github)}
        {--output= : Write the report to a file}
        {--baseline= : Compare findings with a saved baseline}
        {--fail-on-regression : Also fail on increased severity or reopened findings (requires a version 2 baseline)}
        {--base= : Only fail on findings that touch files changed since this ref, for example origin/main}
        {--dirty : Only fail on findings that touch uncommitted files}';

    /**
     * The command description.
     */
    protected $description = 'Fail CI when audit findings meet a severity threshold.';

    public function __construct(
        private readonly ProjectContext $project,
        private readonly FindingLoader $loader,
        private readonly DirtyScope $dirty,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $findingsPath = $this->option('findings');
        $baselinePath = $this->option('baseline');
        $baselineRequested = $baselinePath !== null;
        $hasBaseline = is_string($baselinePath) && $baselinePath !== '';
        $failOnRegression = (bool) $this->option('fail-on-regression');

        if ($failOnRegression && ! $hasBaseline) {
            $this->components->error('--fail-on-regression requires a version 2 --baseline created with --with-status.');

            return self::FAILURE;
        }

        if ($baselineRequested && ! $hasBaseline) {
            $this->components->error('Pass a path to --baseline=path/to/findings-baseline.json.');

            return self::FAILURE;
        }

        if ($hasBaseline && (($this->option('base') !== null && $this->option('base') !== '') || (bool) $this->option('dirty'))) {
            $this->components->error('--baseline cannot be combined with --base or --dirty; compare findings generated with the same full scope.');

            return self::FAILURE;
        }

        if (! is_string($findingsPath) || $findingsPath === '') {
            $this->components->error('Pass --findings=path/to/findings.json (produced by the audit agent).');

            return self::FAILURE;
        }

        try {
            $threshold = Severity::from((string) $this->option('fail-on'));
            $findings = $this->loader->load($findingsPath);
        } catch (ValueError) {
            $this->components->error('Unknown severity ['.$this->option('fail-on').']. Use critical, high, medium, low, or info.');

            return self::FAILURE;
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $meta = [
            'generated_at' => now()->toDateTimeString(),
            'generator' => 'laravel-auditor',
            'mode' => 'ci',
            'fail_on' => $threshold->value,
        ];

        $scoped = $this->scopedFindings($findings);

        if ($scoped === null) {
            return self::FAILURE;
        }

        $findings = $scoped['findings'];
        $meta = [...$meta, ...$scoped['meta']];

        $baselineComparison = null;

        if ($hasBaseline) {
            try {
                $baselineComparison = FindingBaseline::load((string) $baselinePath)->compare($findings);

                if ($failOnRegression && ! $baselineComparison->statusHistoryAvailable) {
                    throw new RuntimeException('--fail-on-regression requires a version 2 baseline created with --with-status; historical status is unknown.');
                }

                if ($failOnRegression) {
                    $meta['baseline_policy'] = 'new_and_regressions';
                }
            } catch (RuntimeException $e) {
                $this->components->error($e->getMessage());

                return self::FAILURE;
            }
        }

        $candidates = $baselineComparison === null
            ? $findings->all()
            : ($failOnRegression ? $baselineComparison->regressionCandidates() : $baselineComparison->new);

        $blocking = new FindingCollection(...array_values(array_filter(
            $candidates,
            static fn (Finding $finding): bool => $finding->status === FindingStatus::Open
                && $finding->severity->weight() >= $threshold->weight(),
        )));

        $report = new AuditReport(
            project: $this->project->facts(),
            domainsRun: $this->project->domainsPresent(),
            findings: $findings,
            meta: $meta,
            baseline: $baselineComparison,
        );

        $format = is_string($this->option('format')) ? $this->option('format') : 'text';

        if (! in_array($format, ['text', 'json', 'sarif', 'github'], true)) {
            $this->components->error("Unknown format [{$format}]. Use text, json, sarif, or github.");

            return self::FAILURE;
        }

        $content = ReportRendererFactory::render($report, $format);

        $output = $this->option('output');

        if (is_string($output) && $output !== '') {
            file_put_contents($output, $content);
            $this->components->info("CI report written to [{$output}].");
        } else {
            if ($format === 'github') {
                $this->output->writeln($content, OutputInterface::OUTPUT_RAW);
            } else {
                $this->line($content);
            }
        }

        if ($baselineComparison !== null) {
            $summary = $baselineComparison->summary();
            $this->components->info(sprintf(
                'Baseline comparison: %d new, %d persistent, %d absent from the current findings file.',
                $summary['new'],
                $summary['persistent'],
                $summary['absent'],
            ));
        }

        if ($blocking->isEmpty()) {
            $findingLabel = $baselineComparison === null ? 'open findings' : ($failOnRegression ? 'new or regressed open findings' : 'new open findings');
            $this->components->info('CI passed: no '.$findingLabel.' at or above '.$threshold->value.'.');

            return self::SUCCESS;
        }

        $this->components->error(sprintf(
            $baselineComparison === null
                ? 'CI failed: %d open finding(s) at or above %s.'
                : ($failOnRegression ? 'CI failed: %d new or regressed open finding(s) at or above %s.' : 'CI failed: %d new open finding(s) at or above %s.'),
            $blocking->count(),
            $threshold->value,
        ));

        return self::FAILURE;
    }

    /**
     * Applies `--base` and `--dirty` when either was passed.
     *
     * @return array{findings: FindingCollection, meta: array<string, bool|int|string>}|null
     */
    private function scopedFindings(FindingCollection $findings): ?array
    {
        $base = $this->option('base');
        $dirty = (bool) $this->option('dirty');
        $baseRef = is_string($base) ? trim($base) : null;

        if ($baseRef === '') {
            $this->components->error('Pass a commit, branch, or tag to --base, for example origin/main.');

            return null;
        }

        if ($baseRef === null && ! $dirty) {
            return ['findings' => $findings, 'meta' => []];
        }

        $scope = $this->dirty->resolve($findings, $baseRef, $dirty);

        if ($scope['ok'] !== true) {
            $this->components->error($scope['message']);
            $this->components->info($scope['hint']);

            return null;
        }

        $this->components->info($scope['summary']);

        return ['findings' => $scope['findings'], 'meta' => $scope['meta']];
    }
}
