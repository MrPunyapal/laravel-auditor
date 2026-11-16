<?php

declare(strict_types=1);

namespace LaravelAuditor\Console\Commands;

use Illuminate\Console\Command;
use LaravelAuditor\Context\Migrations\MigrationRiskAnalyzer;
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
        {--output= : Write structured indicators to JSON instead of stdout}';

    protected $description = 'Identify possible migration risks from two complete database schema snapshots.';

    public function __construct(
        private readonly MigrationRiskAnalyzer $analyzer,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $before = $this->load((string) $this->argument('before'));
            $after = $this->load((string) $this->argument('after'));
            $content = json_encode(
                $this->analyzer->analyze($before, $after),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ).PHP_EOL;
        } catch (Throwable $e) {
            $this->components->error('Migration risk analysis failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $output = $this->option('output');

        if (is_string($output) && $output !== '') {
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
