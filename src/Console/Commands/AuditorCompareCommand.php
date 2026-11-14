<?php

declare(strict_types=1);

namespace LaravelAuditor\Console\Commands;

use Illuminate\Console\Command;
use LaravelAuditor\Context\Snapshots\SnapshotComparator;
use RuntimeException;
use Throwable;

/**
 * Compares saved snapshots without modifying the target application.
 */
class AuditorCompareCommand extends Command
{
    protected $signature = 'auditor:compare
        {before : Path to the previous snapshot JSON}
        {after : Path to the current snapshot JSON}
        {--output= : Write the structured diff to a file instead of stdout}';

    protected $description = 'Compare two Laravel context snapshots.';

    public function __construct(
        private readonly SnapshotComparator $comparator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $before = $this->load((string) $this->argument('before'));
            $after = $this->load((string) $this->argument('after'));

            $content = json_encode(
                $this->comparator->compare($before, $after),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ).PHP_EOL;
        } catch (Throwable $e) {
            $this->components->error('Snapshot comparison failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $output = $this->option('output');

        if (is_string($output) && $output !== '') {
            if (file_put_contents($output, $content) === false) {
                $this->components->error("Comparison could not be written to [{$output}].");

                return self::FAILURE;
            }

            $this->components->info("Comparison written to [{$output}].");

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

        $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($data)) {
            throw new RuntimeException("Snapshot file [{$path}] must contain an object.");
        }

        return $data;
    }
}
