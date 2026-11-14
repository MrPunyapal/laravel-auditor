<?php

declare(strict_types=1);

namespace LaravelAuditor\Console\Commands;

use Illuminate\Console\Command;
use LaravelAuditor\Context\Snapshots\SnapshotBuilder;
use Throwable;

/**
 * Writes a deterministic snapshot for later Laravel behavior comparisons.
 */
class AuditorSnapshotCommand extends Command
{
    protected $signature = 'auditor:snapshot
        {--output= : Write snapshot JSON to a file instead of stdout}
        {--schema : Include read-only live database schema shape}';

    protected $description = 'Capture the current routes and authorization registry as versioned JSON.';

    public function __construct(
        private readonly SnapshotBuilder $builder,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $content = json_encode(
                $this->builder->capture((bool) $this->option('schema')),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ).PHP_EOL;
        } catch (Throwable $e) {
            $this->components->error('Snapshot could not be captured: '.$e->getMessage());

            return self::FAILURE;
        }

        $output = $this->option('output');

        if (is_string($output) && $output !== '') {
            if (file_put_contents($output, $content) === false) {
                $this->components->error("Snapshot could not be written to [{$output}].");

                return self::FAILURE;
            }

            $this->components->info("Snapshot written to [{$output}].");

            return self::SUCCESS;
        }

        $this->line(rtrim($content));

        return self::SUCCESS;
    }
}
