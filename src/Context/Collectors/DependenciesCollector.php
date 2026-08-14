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
