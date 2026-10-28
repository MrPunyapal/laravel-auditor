<?php

declare(strict_types=1);

namespace LaravelAuditor\Context\Collectors;

use Composer\InstalledVersions;
use Illuminate\Filesystem\Filesystem;
use LaravelAuditor\Context\ContextCollector;
use LaravelAuditor\Context\FilterableCollector;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Collects the application's composer dependencies and versions.
 */
final class DependenciesCollector implements ContextCollector, FilterableCollector
{
    public function __construct(
        private readonly Filesystem $files,
    ) {}

    public function name(): string
    {
        return 'dependencies';
    }

    public function description(): string
    {
        return 'List installed composer packages with versions and dev status. Optional composer audit results when enabled. Optional filter: package (substring).';
    }

    public function filters(): array
    {
        return [
            'package' => 'Case-insensitive substring match on the installed package name, e.g. "livewire". Only narrows the packages list; requires, requires_dev, and composer_audit stay complete.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $composer = $this->composerFile();
        $requires = is_array($composer['require'] ?? null) ? $composer['require'] : [];
        $requiresDev = is_array($composer['require-dev'] ?? null) ? $composer['require-dev'] : [];

        $packages = [];

        foreach (array_unique(array_merge(array_keys($requires), array_keys($requiresDev), ['laravel/framework'])) as $package) {
            if (! is_string($package) || $package === '' || $package === 'php') {
                continue;
            }

            if (! InstalledVersions::isInstalled($package)) {
                continue;
            }

            $packages[$package] = [
                'version' => InstalledVersions::getPrettyVersion($package),
                'dev' => isset($requiresDev[$package]),
            ];
        }

        ksort($packages);

        return [
            'count' => count($packages),
            'packages' => $packages,
            'requires' => $requires,
            'requires_dev' => $requiresDev,
            'installed_count' => count(InstalledVersions::getInstalledPackages()),
            'composer_audit' => $this->composerAudit(),
        ];
    }

    public function collectFiltered(array $arguments): array
    {
        $payload = $this->collect();

        if (! isset($arguments['package'])) {
            return $payload;
        }

        $needle = mb_strtolower($arguments['package']);

        $packages = array_filter(
            (array) $payload['packages'],
            fn (array $meta, string $name): bool => str_contains(mb_strtolower($name), $needle),
            ARRAY_FILTER_USE_BOTH,
        );

        $payload['filtered'] = true;
        $payload['total_count'] = count($payload['packages']);
        $payload['count'] = count($packages);
        $payload['packages'] = $packages;

        return $payload;
    }

    /**
     * Best-effort `composer audit` output. Read-only and never mutates the
     * project; returns `available: false` when the binary, network, or a
     * working lock file is missing instead of throwing.
     *
     * @return array<string, mixed>
     */
    private function composerAudit(): array
    {
        if (! config('laravel-auditor.context.composer_audit', true)) {
            return ['available' => false, 'reason' => 'composer audit is disabled by configuration'];
        }

        if (! class_exists(Process::class)) {
            return ['available' => false, 'reason' => 'symfony/process is not installed'];
        }

        $lock = base_path('composer.lock');

        if (! $this->files->exists($lock)) {
            return ['available' => false, 'reason' => 'composer.lock is missing'];
        }

        $locked = (bool) config('laravel-auditor.context.composer_audit_locked', false);
        $noDev = (bool) config('laravel-auditor.context.composer_audit_no_dev', false);
        $process = new Process(self::composerAuditCommand($locked, $noDev), base_path());

        try {
            $process->setTimeout(60);
            $process->run();
        } catch (Throwable $e) {
            return ['available' => false, 'reason' => 'composer audit could not run: '.$e->getMessage()];
        }

        $output = json_decode($process->getOutput(), true);
        $parsed = is_array($output) ? ComposerAuditResultParser::parse($output, $process->getExitCode()) : null;

        if ($parsed === null) {
            return [
                'available' => false,
                'reason' => self::composerAuditFailureReason(
                    $process->getExitCode(),
                    $process->getOutput(),
                    $process->getErrorOutput(),
                ),
            ];
        }

        // Composer returns non-zero for security findings, not only failures.
        // A JSON payload with missing repositories is usable but incomplete.
        if (! $parsed['complete']) {
            $parsed['reason'] = 'Some Composer advisory repositories were unreachable';
        }

        return [...$parsed, 'mode' => $locked ? 'locked' : 'installed', 'scope' => $noDev ? 'production' : 'all'];
    }

    /**
     * Build the read-only Composer command for the requested audit target.
     *
     * @return list<string>
     */
    public static function composerAuditCommand(bool $locked = false, bool $noDev = false): array
    {
        return [
            'composer',
            'audit',
            ...($locked ? ['--locked'] : []),
            ...($noDev ? ['--no-dev'] : []),
            '--format=json',
            '--no-interaction',
            '--no-plugins',
        ];
    }

    /**
     * Explain a failed `composer audit --format=json` run using the process
     * exit code and stderr, so agents do not treat a network or Composer
     * error as a JSON parsing problem.
     */
    public static function composerAuditFailureReason(?int $exitCode, string $stdout, string $stderr): string
    {
        $exit = $exitCode ?? -1;
        $stderr = self::truncateDiagnostic($stderr);

        if ($stderr !== '') {
            return "composer audit failed (exit {$exit}): {$stderr}";
        }

        $stdout = self::truncateDiagnostic($stdout);

        if ($stdout !== '') {
            return "composer audit produced no parseable JSON output (exit {$exit}): {$stdout}";
        }

        return "composer audit produced no parseable JSON output (exit {$exit})";
    }

    private static function truncateDiagnostic(string $value, int $limit = 300): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($value));
        $value = is_string($normalized) ? $normalized : trim($value);

        if (strlen($value) <= $limit) {
            return $value;
        }

        return rtrim(substr($value, 0, $limit - 3)).'...';
    }

    /**
     * @return array<string, mixed>
     */
    private function composerFile(): array
    {
        $path = base_path('composer.json');

        if (! $this->files->exists($path)) {
            return [];
        }

        $data = json_decode((string) $this->files->get($path), true);

        return is_array($data) ? $data : [];
    }
}
