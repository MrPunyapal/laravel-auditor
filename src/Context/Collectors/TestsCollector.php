<?php

declare(strict_types=1);

namespace LaravelAuditor\Context\Collectors;

use Illuminate\Filesystem\Filesystem;
use LaravelAuditor\Context\ContextCollector;
use Pest\TestSuite;
use Pest\Version;
use SplFileInfo;

/**
 * Collects context about the application's test suite.
 *
 * Reports file counts and naming conventions rather than executing tests,
 * keeping the collector safe and fast.
 */
final class TestsCollector implements ContextCollector
{
    public function __construct(
        private readonly Filesystem $files,
    ) {}

    public function name(): string
    {
        return 'tests';
    }

    public function description(): string
    {
        return 'Describe the test suite: framework, file counts, and conventions.';
    }

    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $tests = $this->testFiles();

        return [
            'framework' => $this->framework(),
            'count' => count($tests),
            'feature_tests' => count(array_filter($tests, fn (string $path): bool => str_contains($path, 'Feature') || str_contains($path, 'feature'))),
            'unit_tests' => count(array_filter($tests, fn (string $path): bool => str_contains($path, 'Unit') || str_contains($path, 'unit'))),
            'uses_pest' => $this->usesPest(),
            'files' => $tests,
        ];
    }

    /**
     * @return list<string>
     */
    private function testFiles(): array
    {
        $directory = base_path('tests');

        if (! $this->files->isDirectory($directory)) {
            return [];
        }

        return array_values(array_map(
            static fn (SplFileInfo $file): string => $file->getRelativePathname(),
            $this->files->allFiles($directory),
        ));
    }

    private function framework(): string
    {
        if ($this->files->exists(base_path('tests/Pest.php'))) {
            return 'pest';
        }

        if ($this->usesPest()) {
            return 'pest';
        }

        return 'phpunit';
    }

    private function usesPest(): bool
    {
        if ($this->files->exists(base_path('tests/Pest.php'))) {
            return true;
        }

        if (class_exists(TestSuite::class) || class_exists(Version::class)) {
            return true;
        }

        return false;
    }
}
