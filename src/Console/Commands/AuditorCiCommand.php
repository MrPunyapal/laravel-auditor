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
use LaravelAuditor\Audit\Reports\JsonReportRenderer;
use LaravelAuditor\Audit\Reports\SarifReportRenderer;
use LaravelAuditor\Audit\Reports\TextReportRenderer;
use LaravelAuditor\Context\ProjectContext;
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
        {--output= : Write the report to a file}';

    /**
     * The command description.
     */
    protected $description = 'Fail CI when audit findings meet a severity threshold.';

    public function __construct(
        private readonly ProjectContext $project,
        private readonly FindingLoader $loader,
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

        $blocking = new FindingCollection(...array_values(array_filter(
            $findings->all(),
            static fn (Finding $finding): bool => $finding->status === FindingStatus::Open
                && $finding->severity->weight() >= $threshold->weight(),
        )));

        $report = new AuditReport(
            project: $this->project->facts(),
            domainsRun: $this->project->domainsPresent(),
            findings: $findings,
            meta: [
                'generated_at' => now()->toDateTimeString(),
                'generator' => 'laravel-auditor',
                'mode' => 'ci',
                'fail_on' => $threshold->value,
            ],
        );

        $format = is_string($this->option('format')) ? $this->option('format') : 'text';

        if (! in_array($format, ['text', 'json', 'sarif'], true)) {
            $this->components->error("Unknown format [{$format}]. Use text, json, or sarif.");

            return self::FAILURE;
        }

        $content = match ($format) {
            'json' => (new JsonReportRenderer)->render($report),
            'sarif' => (new SarifReportRenderer)->render($report),
            default => (new TextReportRenderer)->render($report),
        };

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
}
