<?php

declare(strict_types=1);

namespace LaravelAuditor\Context\Collectors;

use Composer\InstalledVersions;
use Illuminate\Filesystem\Filesystem;
use LaravelAuditor\Context\ContextCollector;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Collects the application's composer dependencies and versions.
 */
final class DependenciesCollector implements ContextCollector
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
        return 'List installed composer packages with versions and dev status. Optional composer audit results when enabled.';
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

    /**
     * Best-effort `composer audit` output. Read-only and never mutates the
     * project; returns `available: false` when the binary, network, or a
     * working lock file is missing instead of throwing.
     *
     * @return array{available: bool, reason?: string, count?: int, advisories?: list<array{package: string, advisory_id: string, title: string, severity: string, affected_versions: string, link: string, cve: string}>}
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

        $process = new Process(['composer', 'audit', '--format=json', '--no-interaction', '--no-plugins'], base_path());

        try {
            $process->setTimeout(60);
            $process->run();
        } catch (Throwable $e) {
            return ['available' => false, 'reason' => 'composer audit could not run: '.$e->getMessage()];
        }

        $output = json_decode($process->getOutput(), true);

        if (! is_array($output)) {
            return ['available' => false, 'reason' => 'composer audit produced no parseable JSON output'];
        }

        $advisories = [];

        foreach (($output['advisories'] ?? []) as $package => $list) {
            foreach ((array) $list as $advisory) {
                if (! is_array($advisory)) {
                    continue;
                }

                $advisories[] = [
                    'package' => is_string($package) ? $package : '',
                    'advisory_id' => is_string($advisory['advisoryId'] ?? null) ? $advisory['advisoryId'] : '',
                    'title' => is_string($advisory['title'] ?? null) ? $advisory['title'] : '',
                    'severity' => is_string($advisory['severity'] ?? null) ? $advisory['severity'] : '',
                    'affected_versions' => is_string($advisory['affectedVersions'] ?? null) ? $advisory['affectedVersions'] : '',
                    'link' => is_string($advisory['link'] ?? null) ? $advisory['link'] : '',
                    'cve' => is_string($advisory['cve'] ?? null) ? $advisory['cve'] : '',
                ];
            }
        }

        return [
            'available' => true,
            'count' => count($advisories),
            'advisories' => $advisories,
        ];
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
