<?php

declare(strict_types=1);

namespace LaravelAuditor\Console\Commands;

use Illuminate\Console\Command;
use LaravelAuditor\Audit\Enums\FindingStatus;
use LaravelAuditor\Audit\Enums\Severity;
use LaravelAuditor\Audit\Findings\Finding;
use LaravelAuditor\Audit\Findings\FindingCollection;
use LaravelAuditor\Audit\Findings\FindingLoader;
use LaravelAuditor\Audit\Reports\AuditReport;
use LaravelAuditor\Audit\Reports\ReportRendererFactory;
use LaravelAuditor\Context\ProjectContext;
use LaravelAuditor\Support\DirtyScope;
use RuntimeException;
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
        {--format=text : Output format (text, json, sarif)}
        {--output= : Write the report to a file}
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

        $blocking = new FindingCollection(...array_values(array_filter(
            $findings->all(),
            static fn (Finding $finding): bool => $finding->status === FindingStatus::Open
                && $finding->severity->weight() >= $threshold->weight(),
        )));

        $report = new AuditReport(
            project: $this->project->facts(),
            domainsRun: $this->project->domainsPresent(),
            findings: $findings,
            meta: $meta,
        );

        $format = is_string($this->option('format')) ? $this->option('format') : 'text';

        if (! in_array($format, ['text', 'json', 'sarif'], true)) {
            $this->components->error("Unknown format [{$format}]. Use text, json, or sarif.");

            return self::FAILURE;
        }

        $content = ReportRendererFactory::render($report, $format);

        $output = $this->option('output');

        if (is_string($output) && $output !== '') {
            file_put_contents($output, $content);
            $this->components->info("CI report written to [{$output}].");
        } else {
            $this->line($content);
        }

        if ($blocking->isEmpty()) {
            $this->components->info('CI passed: no open findings at or above '.$threshold->value.'.');

            return self::SUCCESS;
        }

        $this->components->error(sprintf(
            'CI failed: %d open finding(s) at or above %s.',
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
