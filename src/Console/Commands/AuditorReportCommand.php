<?php

declare(strict_types=1);

namespace LaravelAuditor\Console\Commands;

use Illuminate\Console\Command;
use JsonException;
use LaravelAuditor\Audit\Findings\Finding;
use LaravelAuditor\Audit\Findings\FindingCollection;
use LaravelAuditor\Audit\Reports\AuditReport;
use LaravelAuditor\Audit\Reports\JsonReportRenderer;
use LaravelAuditor\Audit\Reports\MarkdownReportRenderer;
use LaravelAuditor\Audit\Reports\TextReportRenderer;
use LaravelAuditor\Context\ProjectContext;

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
        {--format=markdown : Output format (markdown, json, text)}
        {--output= : Write the report to a file instead of stdout}';

    /**
     * The command description.
     */
    protected $description = 'Generate an audit report.';

    public function __construct(
        private readonly ProjectContext $project,
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
            $findings = $this->loadFindings($findingsPath);

            if ($findings === null) {
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

        if (! in_array($format, ['markdown', 'json', 'text'], true)) {
            $this->components->error("Unknown format [{$format}]. Use markdown, json, or text.");

            return self::FAILURE;
        }

        $content = match ($format) {
            'json' => (new JsonReportRenderer)->render($report),
            'text' => (new TextReportRenderer)->render($report),
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

    private function loadFindings(string $path): ?FindingCollection
    {
        if (! file_exists($path)) {
            $this->components->error("Findings file [{$path}] does not exist.");

            return null;
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->components->error('Invalid findings JSON: '.$e->getMessage());

            return null;
        }

        if (! is_array($data)) {
            $this->components->error('Findings file must contain a JSON array of findings.');

            return null;
        }

        $list = is_array($data['findings'] ?? null) ? $data['findings'] : $data;

        return FindingCollection::fromIterable(array_map(
            static fn (array $item): Finding => Finding::fromArray($item),
            $list,
        ));
    }
}
