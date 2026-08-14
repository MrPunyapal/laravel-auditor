<?php

declare(strict_types=1);

namespace LaravelAuditor\Console\Commands;

use Illuminate\Console\Command;
use LaravelAuditor\Audit\Findings\FindingCollection;
use LaravelAuditor\Audit\Findings\FindingLoader;
use LaravelAuditor\Audit\Reports\AuditReport;
use LaravelAuditor\Audit\Reports\JsonReportRenderer;
use LaravelAuditor\Audit\Reports\MarkdownReportRenderer;
use LaravelAuditor\Audit\Reports\SarifReportRenderer;
use LaravelAuditor\Audit\Reports\TextReportRenderer;
use LaravelAuditor\Context\ProjectContext;
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
        {--format=markdown : Output format (markdown, json, text, sarif)}
        {--output= : Write the report to a file instead of stdout}';

    /**
     * The command description.
     */
    protected $description = 'Generate an audit report.';

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
        $example = (bool) $this->option('example');

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

        $domainsRun = $this->project->domainsPresent();

        $report = new AuditReport(
            project: $this->project->facts(),
            domainsRun: $domainsRun,
            findings: $findings,
            meta: [
                'generated_at' => now()->toDateTimeString(),
                'generator' => 'laravel-auditor',
            ],
        );

        $defaultFormat = (string) config('laravel-auditor.report.format', 'markdown');
        $format = is_string($this->option('format')) ? $this->option('format') : $defaultFormat;

        if (! in_array($format, ['markdown', 'json', 'text', 'sarif'], true)) {
            $this->components->error("Unknown format [{$format}]. Use markdown, json, text, or sarif.");

            return self::FAILURE;
        }

        $content = match ($format) {
            'json' => (new JsonReportRenderer)->render($report),
            'text' => (new TextReportRenderer)->render($report),
            'sarif' => (new SarifReportRenderer)->render($report),
            default => (new MarkdownReportRenderer)->render($report),
        };

        $output = $this->option('output');

        if (is_string($output) && $output !== '') {
            file_put_contents($output, $content);
            $this->components->info("Report written to [{$output}].");

            return self::SUCCESS;
        }

        $this->line($content);

        return self::SUCCESS;
    }
}
