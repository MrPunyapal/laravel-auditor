<?php

declare(strict_types=1);

namespace LaravelAuditor\Context\Collectors;

use Composer\InstalledVersions;
use Illuminate\Filesystem\Filesystem;
use LaravelAuditor\Context\ContextCollector;

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
        return 'List installed composer packages with versions and dev status.';
    }

    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $packages = [];

        foreach (InstalledVersions::getInstalledPackages() as $package) {
            $packages[$package] = [
                'version' => InstalledVersions::getPrettyVersion($package),
                'dev' => $this->isDevPackage($package),
            ];
        }

        ksort($packages);

        $composer = $this->composerFile();

        return [
            'count' => count($packages),
            'packages' => $packages,
            'requires' => $composer['require'] ?? [],
            'requires_dev' => $composer['require-dev'] ?? [],
        ];
    }

    private function isDevPackage(string $package): bool
    {
        $composer = $this->composerFile();

        return isset($composer['require-dev'][$package]);
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
