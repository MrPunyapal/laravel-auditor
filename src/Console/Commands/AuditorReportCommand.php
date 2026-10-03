<?php

declare(strict_types=1);

namespace LaravelAuditor\Console\Commands;

use Illuminate\Console\Command;
use LaravelAuditor\Audit\Findings\FindingCollection;
use LaravelAuditor\Audit\Findings\FindingLoader;
use LaravelAuditor\Audit\Reports\AuditReport;
use LaravelAuditor\Audit\Reports\ReportRendererFactory;
use LaravelAuditor\Context\ProjectContext;
use LaravelAuditor\Support\DirtyScope;
use RuntimeException;

/**
 * Generates an audit report from provided findings or project context alone.
 */
class AuditorReportCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'auditor:report
        {--findings= : Path to a JSON file containing findings}
        {--example : Render the packaged example findings}
        {--format= : Output format (markdown, json, text, sarif)}
        {--output= : Write the report to a file instead of stdout}
        {--base= : Limit the report to findings that touch files changed since this ref, for example origin/main}
        {--dirty : Limit the report to findings touching uncommitted files}';

    /**
     * The command description.
     */
    protected $description = 'Generate an audit report.';

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
        $example = (bool) $this->option('example');

        if ($example && is_string($findingsPath) && $findingsPath !== '') {
            $this->components->error('Pass either --findings or --example, not both.');

            return self::FAILURE;
        }

        $findings = new FindingCollection;

        if ($example) {
            $findingsPath = __DIR__.'/../../../resources/auditor/examples/findings.json';
        }

        if (is_string($findingsPath) && $findingsPath !== '') {
            try {
                $findings = $this->loader->load($findingsPath);
            } catch (RuntimeException $e) {
                $this->components->error($e->getMessage());

                return self::FAILURE;
            }
        }

        $meta = [
            'generated_at' => now()->toDateTimeString(),
            'generator' => 'laravel-auditor',
        ];

        $scoped = $this->scopedFindings($findings);

        if ($scoped === null) {
            return self::FAILURE;
        }

        $findings = $scoped['findings'];
        $meta = [...$meta, ...$scoped['meta']];

        $domainsRun = $this->project->domainsPresent();

        $report = new AuditReport(
            project: $this->project->facts(),
            domainsRun: $domainsRun,
            findings: $findings,
            meta: $meta,
        );

        $option = $this->option('format');
        $format = is_string($option) && $option !== ''
            ? $option
            : (string) config('laravel-auditor.report.format', 'markdown');

        if (! in_array($format, ['markdown', 'json', 'text', 'sarif'], true)) {
            $this->components->error("Unknown format [{$format}]. Use markdown, json, text, or sarif.");

            return self::FAILURE;
        }

        $content = ReportRendererFactory::render($report, $format);

        $output = $this->option('output');

        if (is_string($output) && $output !== '') {
            file_put_contents($output, $content);
            $this->components->info("Report written to [{$output}].");

            return self::SUCCESS;
        }

        $this->line($content);

        return self::SUCCESS;
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
