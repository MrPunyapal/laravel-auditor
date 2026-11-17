<?php

declare(strict_types=1);

namespace LaravelAuditor\Console\Commands;

use Illuminate\Console\Command;
use LaravelAuditor\Context\Migrations\MigrationDriverAdvisor;
use LaravelAuditor\Context\Migrations\MigrationRiskAnalyzer;
use LaravelAuditor\Context\Migrations\MigrationSourceReview;
use RuntimeException;
use Throwable;

/**
 * Reviews structural migration risk indicators from saved schema snapshots.
 */
final class AuditorMigrationRiskCommand extends Command
{
    protected $signature = 'auditor:migration-risk
        {before : Path to a schema-enabled snapshot before the change}
        {after : Path to a schema-enabled snapshot after the change}
        {--migration=* : Analyze a registered migration PHP file (repeatable)}
        {--driver-guidance : Include conditional database-specific review questions}
        {--output= : Write structured indicators to JSON instead of stdout}';

    protected $description = 'Identify possible migration risks from two complete database schema snapshots.';

    public function __construct(
        private readonly MigrationRiskAnalyzer $analyzer,
        private readonly MigrationDriverAdvisor $advisor,
        private readonly MigrationSourceReview $sourceReview,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $beforePath = (string) $this->argument('before');
            $afterPath = (string) $this->argument('after');
            $before = $this->load($beforePath);
            $after = $this->load($afterPath);
            $analysis = $this->analyzer->analyze($before, $after);
            $migrations = array_values(array_map(static fn (?string $path): string => $path ?? '', $this->option('migration')));

            if ($migrations !== []) {
                $analysis['source_review'] = $this->sourceReview->review($migrations);
                $analysis['coverage'] = 'snapshot_structure_plus_selected_php_source';
                $analysis['limitations'][0] = 'Only recognized patterns in explicitly selected migration source files were inspected. Other migration code was not analyzed.';
            }

            if ((bool) $this->option('driver-guidance')) {
                $analysis['driver_review'] = $this->advisor->review($analysis);
            }

            $content = json_encode(
                $analysis,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ).PHP_EOL;
        } catch (Throwable $e) {
            $this->components->error('Migration risk analysis failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $output = $this->option('output');

        if (is_string($output) && $output !== '') {
            $migrationPaths = array_map(fn (string $path): string => $this->migrationPath($path), $migrations);

            if ($this->outputResolvesToInput($output, $beforePath, $afterPath, ...$migrationPaths)) {
                $this->components->error('--output must not resolve to an input snapshot or migration file.');

                return self::FAILURE;
            }

            if (@file_put_contents($output, $content) === false) {
                $this->components->error("Risk analysis could not be written to [{$output}].");

                return self::FAILURE;
            }

            $this->components->info("Migration risk indicators written to [{$output}].");

            return self::SUCCESS;
        }

        $this->line(rtrim($content));

        return self::SUCCESS;
    }

    private function outputResolvesToInput(string $output, string ...$inputs): bool
    {
        $outputPath = realpath($output);

        if ($outputPath === false) {
            return false;
        }

        foreach ($inputs as $input) {
            $inputPath = realpath($input);
            $outputStat = @stat($output);
            $inputStat = @stat($input);
            $sameFile = $inputPath !== false && (DIRECTORY_SEPARATOR === '\\'
                ? strcasecmp($outputPath, $inputPath) === 0
                : $outputPath === $inputPath);
            $sameFileIdentity = $outputStat !== false && $inputStat !== false
                && $outputStat['dev'] === $inputStat['dev']
                && $outputStat['ino'] !== 0
                && $outputStat['ino'] === $inputStat['ino'];

            if ($sameFile || $sameFileIdentity) {
                return true;
            }
        }

        return false;
    }

    private function migrationPath(string $path): string
    {
        if (str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:[\\\\\\/]/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }

    /**
     * @return array<string, mixed>
     */
    private function load(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("Snapshot file [{$path}] is missing or unreadable.");
        }

        $content = file_get_contents($path);

        if (! is_string($content)) {
            throw new RuntimeException("Snapshot file [{$path}] could not be read.");
        }

        $data = json_decode($content, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($data) || ! array_is_list($data) && ! array_key_exists('sections', $data)) {
            throw new RuntimeException("Snapshot file [{$path}] must contain a snapshot object.");
        }

        return $data;
    }
}
