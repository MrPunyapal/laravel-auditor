<?php

declare(strict_types=1);

namespace LaravelAuditor\Context\Collectors;

use Illuminate\Filesystem\Filesystem;
use LaravelAuditor\Context\ContextCollector;
use LaravelAuditor\Support\ApplicationPaths;
use Pest\TestSuite;
use Pest\Version;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Collects context about the application's test suite.
 *
 * Reports accurate test case counts by best-effort `pest --list-tests` /
 * `phpunit --list-tests` discovery (read-only, never executes tests), falling
 * back to file counts when listing is unavailable.
 */
final class TestsCollector implements ContextCollector
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly ApplicationPaths $paths,
    ) {}

    public function name(): string
    {
        return 'tests';
    }

    public function description(): string
    {
        return 'Describe the test suite: framework, test case counts, file layout, and conventions.';
    }

    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $tests = $this->testFiles();

        $listing = $this->listTests();

        $featureFiles = array_filter($tests, fn (string $path): bool => str_contains($path, 'Feature') || str_contains($path, 'feature'));
        $unitFiles = array_filter($tests, fn (string $path): bool => str_contains($path, 'Unit') || str_contains($path, 'unit'));

        return [
            'framework' => $this->framework(),
            'uses_pest' => $this->usesPest(),
            'count' => $listing['total'] ?? count($tests),
            'feature_tests' => $listing['feature'] ?? count($featureFiles),
            'unit_tests' => $listing['unit'] ?? count($unitFiles),
            'count_source' => $listing !== null ? 'list-tests' : 'file-count',
            'file_count' => count($tests),
            'feature_file_count' => count($featureFiles),
            'unit_file_count' => count($unitFiles),
            'files' => $tests,
        ];
    }

    /**
     * @return list<string>
     */
    private function testFiles(): array
    {
        $files = [];

        foreach ($this->paths->siblings('tests') as $directory) {
            if (! $this->files->isDirectory($directory)) {
                continue;
            }

            foreach ($this->files->allFiles($directory) as $file) {
                $relative = str_replace('\\', '/', $file->getRelativePathname());

                if ($this->looksLikeTestFile($relative)) {
                    $files[] = $relative;
                }
            }
        }

        return array_values(array_unique($files));
    }

    private function looksLikeTestFile(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);
        $basename = basename($normalized);

        if (! str_ends_with(strtolower($basename), '.php')) {
            return false;
        }

        if (in_array($basename, ['Pest.php', 'TestCase.php', 'CreatesApplication.php'], true)) {
            return false;
        }

        if (preg_match('/(^|\/)(Fixtures?|fixtures?|snapshots?)(\/|$)/', $normalized) === 1) {
            return false;
        }

        if (str_ends_with($basename, 'Test.php') || str_ends_with($basename, 'Tests.php')) {
            return true;
        }

        return preg_match('/(^|\/)(Feature|Unit|feature|unit)\//', $normalized) === 1;
    }

    private function framework(): string
    {
        if ($this->hasPestBootstrap()) {
            return 'pest';
        }

        if ($this->usesPest()) {
            return 'pest';
        }

        return 'phpunit';
    }

    private function hasPestBootstrap(): bool
    {
        foreach ($this->paths->siblings('tests') as $directory) {
            if ($this->files->exists($directory.DIRECTORY_SEPARATOR.'Pest.php')) {
                return true;
            }
        }

        return false;
    }

    private function usesPest(): bool
    {
        if ($this->hasPestBootstrap()) {
            return true;
        }

        if (class_exists(TestSuite::class) || class_exists(Version::class)) {
            return true;
        }

        return false;
    }

    /**
     * Best-effort discovery of the exact test case count. Runs the test
     * runner in list mode (read-only, executes no tests) and counts the
     * expanded test cases. Returns null when the runner, listing, or config
     * prevents a reliable count.
     *
     * @return array{total: int, feature: int, unit: int}|null
     */
    private function listTests(): ?array
    {
        if (! config('laravel-auditor.context.test_listing', false)) {
            return null;
        }

        if (! class_exists(Process::class)) {
            return null;
        }

        $binary = $this->usesPest() ? 'vendor/bin/pest' : 'vendor/bin/phpunit';

        if (! $this->files->exists(base_path($binary))) {
            return null;
        }

        $process = new Process([PHP_BINARY, $binary, '--list-tests'], base_path());

        try {
            $process->setTimeout(60);
            $process->run();
        } catch (Throwable) {
            return null;
        }

        if (! $process->isSuccessful()) {
            return null;
        }

        $listing = self::parseTestListing($process->getOutput());

        return $listing['total'] > 0 ? $listing : null;
    }

    /**
     * Parses the runner's `--list-tests` output into feature/unit/total
     * test case counts. Each listed test case appears on a line beginning
     * with ` - `, including dataset-expanded cases.
     *
     * @return array{total: int, feature: int, unit: int}
     */
    public static function parseTestListing(string $output): array
    {
        $total = 0;
        $feature = 0;
        $unit = 0;

        foreach (explode("\n", $output) as $line) {
            if (preg_match('/^\s*-\s+\S+::/', $line) !== 1) {
                continue;
            }

            $total++;

            if (str_contains($line, 'Tests\\Feature') || str_contains($line, 'tests\\Feature')) {
                $feature++;
            } elseif (str_contains($line, 'Tests\\Unit') || str_contains($line, 'tests\\Unit')) {
                $unit++;
            }
        }

        return ['total' => $total, 'feature' => $feature, 'unit' => $unit];
    }
}
