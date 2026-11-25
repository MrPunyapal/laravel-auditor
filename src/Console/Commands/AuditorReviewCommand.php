<?php

declare(strict_types=1);

namespace LaravelAuditor\Console\Commands;

use Illuminate\Console\Command;
use LaravelAuditor\Audit\Findings\FindingBaseline;
use LaravelAuditor\Audit\Findings\FindingLoader;
use LaravelAuditor\Context\Reviews\ChangeReviewBuilder;
use LaravelAuditor\Context\Reviews\ChangeReviewRenderer;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Builds one deterministic, read-only report from previously saved evidence.
 */
final class AuditorReviewCommand extends Command
{
    protected $signature = 'auditor:review
        {before : Path to the earlier Laravel context snapshot}
        {after : Path to the later Laravel context snapshot}
        {--findings= : Optional complete current findings JSON}
        {--baseline= : Optional historical findings baseline (requires --findings)}
        {--migration=* : Inspect an explicitly selected registered migration PHP file}
        {--driver-guidance : Include conditional database-driver review questions}
        {--format=json : Output format (json, markdown, text)}
        {--output= : Write the report to a file instead of stdout}';

    protected $description = 'Combine saved snapshots, optional schema risks, and supplied audit findings.';

    public function __construct(
        private readonly ChangeReviewBuilder $review,
        private readonly FindingLoader $loader,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $beforePath = (string) $this->argument('before');
            $afterPath = (string) $this->argument('after');
            $findingsPath = $this->optionalPath('findings');
            $baselinePath = $this->optionalPath('baseline');

            if ($baselinePath !== null && $findingsPath === null) {
                throw new RuntimeException('--baseline requires --findings with a complete current audit.');
            }

            $before = $this->loadSnapshot($beforePath);
            $after = $this->loadSnapshot($afterPath);
            $findings = $findingsPath === null ? null : $this->loader->load($findingsPath);
            $baseline = $baselinePath === null ? null : FindingBaseline::load($baselinePath);

            $format = $this->option('format');

            if (! is_string($format) || ! in_array($format, ['json', 'markdown', 'text'], true)) {
                throw new RuntimeException('Unknown review format. Use json, markdown, or text.');
            }

            $migrations = array_values(array_map(static fn (?string $path): string => $path ?? '', $this->option('migration')));
            $report = $this->review->review($before, $after, $findings, $baseline, $migrations, (bool) $this->option('driver-guidance'));
            $content = ChangeReviewRenderer::render($report, $format);

            $output = $this->optionalPath('output');

            if ($output !== null) {
                $migrationPaths = array_map($this->migrationPath(...), $migrations);
                $inputs = array_values(array_filter([$beforePath, $afterPath, $findingsPath, $baselinePath, ...$migrationPaths], is_string(...)));

                if ($this->outputOverwritesInput($output, $inputs)) {
                    throw new RuntimeException('--output must not overwrite any input snapshot, findings file, baseline, or migration.');
                }

                if (@file_put_contents($output, $content) === false) {
                    throw new RuntimeException("Change review could not be written to [{$output}].");
                }

                $this->components->info("Change review written to [{$output}].");

                return self::SUCCESS;
            }

            $this->output->writeln(rtrim($content), OutputInterface::OUTPUT_RAW);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->components->error('Change review failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function migrationPath(string $path): string
    {
        if (str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:[\\\\\\/]/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }

    private function optionalPath(string $option): ?string
    {
        $value = $this->option($option);

        if ($value === null) {
            return null;
        }

        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException("Pass a non-empty path to --{$option}.");
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadSnapshot(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("Snapshot [{$path}] is missing or unreadable.");
        }

        $contents = file_get_contents($path);

        if (! is_string($contents)) {
            throw new RuntimeException("Snapshot [{$path}] could not be read.");
        }

        $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($data) || array_is_list($data)) {
            throw new RuntimeException("Snapshot [{$path}] must contain a JSON object.");
        }

        return $data;
    }

    /**
     * @param  list<string>  $inputs
     */
    private function outputOverwritesInput(string $output, array $inputs): bool
    {
        $outputPath = realpath($output);

        if ($outputPath === false) {
            return false;
        }

        $outputStat = @stat($output);

        foreach ($inputs as $input) {
            $inputPath = realpath($input);
            $inputStat = @stat($input);

            if ($inputPath !== false && (DIRECTORY_SEPARATOR === '\\'
                ? strcasecmp($outputPath, $inputPath) === 0
                : $outputPath === $inputPath)) {
                return true;
            }

            if ($outputStat !== false && $inputStat !== false
                && $outputStat['dev'] === $inputStat['dev']
                && $outputStat['ino'] !== 0 && $outputStat['ino'] === $inputStat['ino']) {
                return true;
            }
        }

        return false;
    }
}
