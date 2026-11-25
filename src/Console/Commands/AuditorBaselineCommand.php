<?php

declare(strict_types=1);

namespace LaravelAuditor\Console\Commands;

use Illuminate\Console\Command;
use LaravelAuditor\Audit\Findings\FindingBaseline;
use LaravelAuditor\Audit\Findings\FindingLoader;
use RuntimeException;
use Throwable;

/**
 * Creates a deterministic findings baseline from an explicit findings file.
 */
final class AuditorBaselineCommand extends Command
{
    protected $signature = 'auditor:baseline
        {--findings= : Path to a JSON file containing findings}
        {--output= : Path to write the versioned findings baseline}
        {--with-status : Create a version 2 baseline containing finding lifecycle status}';

    protected $description = 'Create a deterministic baseline from a findings file.';

    public function __construct(
        private readonly FindingLoader $loader,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $findingsPath = $this->option('findings');
        $outputPath = $this->option('output');

        if (! is_string($findingsPath) || $findingsPath === '') {
            $this->components->error('Pass --findings=path/to/findings.json.');

            return self::FAILURE;
        }

        if (! is_string($outputPath) || $outputPath === '') {
            $this->components->error('Pass --output=path/to/findings-baseline.json.');

            return self::FAILURE;
        }

        try {
            $findings = $this->loader->load($findingsPath);
            $baseline = FindingBaseline::fromFindings($findings, (bool) $this->option('with-status'));
            $content = $baseline->toJson();
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->components->error('Findings baseline could not be created: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($this->outputResolvesToInput($outputPath, $findingsPath)) {
            $this->components->error('--output must not resolve to the input findings file.');

            return self::FAILURE;
        }

        if (@file_put_contents($outputPath, $content, LOCK_EX) === false) {
            $this->components->error("Findings baseline could not be written to [{$outputPath}].");

            return self::FAILURE;
        }

        $this->components->info("Findings baseline written to [{$outputPath}].");

        return self::SUCCESS;
    }

    private function outputResolvesToInput(string $output, string $input): bool
    {
        $outputPath = realpath($output);
        $inputPath = realpath($input);
        $outputStat = @stat($output);
        $inputStat = @stat($input);
        $sameFile = $outputPath !== false && $inputPath !== false && (DIRECTORY_SEPARATOR === '\\'
            ? strcasecmp($outputPath, $inputPath) === 0
            : $outputPath === $inputPath);
        $sameFileIdentity = $outputStat !== false && $inputStat !== false
            && $outputStat['dev'] === $inputStat['dev']
            && $outputStat['ino'] !== 0
            && $outputStat['ino'] === $inputStat['ino'];

        return $sameFile || $sameFileIdentity;
    }
}
