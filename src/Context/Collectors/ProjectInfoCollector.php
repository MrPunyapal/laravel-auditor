<?php

declare(strict_types=1);

namespace LaravelAuditor\Context\Collectors;

use Composer\InstalledVersions;
use Filament\FilamentServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use LaravelAuditor\Context\ContextCollector;
use Livewire\Livewire;

/**
 * Collects deterministic project facts used by the Discover phase.
 *
 * Facts come from the running application, the installed composer packages,
 * and the filesystem — never from model guesses.
 */
final class ProjectInfoCollector implements ContextCollector
{
    public function __construct(
        private readonly Application $app,
        private readonly Filesystem $files,
    ) {}

    public function name(): string
    {
        return 'project_info';
    }

    public function description(): string
    {
        return 'Read PHP & Laravel versions, application type, database engine, ecosystem packages, and source layout.';
    }

    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $installed = $this->packages();

        return [
            'name' => $this->appName(),
            'environment' => $this->app->environment(),
            'php_version' => PHP_VERSION,
            'laravel_version' => InstalledVersions::getVersion('laravel/framework'),
            'laravel_major_version' => $this->laravelMajorVersion(),
            'database' => $this->databaseEngine(),
            'test_framework' => $this->testFramework($installed),
            'frontend' => $this->frontendSignals(),
            'architecture_signals' => $this->architectureSignals(),
            'source_layout' => $this->sourceLayout(),
            'packages' => $installed,
            'paths' => [
                'app' => $this->relative(app_path()),
                'config' => $this->relative(config_path()),
                'database' => $this->relative(database_path()),
                'routes' => $this->relative(base_path('routes')),
                'resources' => $this->relative(resource_path()),
                'tests' => $this->relative(base_path('tests')),
            ],
        ];
    }

    private function appName(): string
    {
        $name = (string) config('app.name', '');

        return $name !== '' ? $name : basename(base_path());
    }

    private function laravelMajorVersion(): ?int
    {
        $version = InstalledVersions::getVersion('laravel/framework');

        return $version !== null ? (int) explode('.', $version)[0] : null;
    }

    private function databaseEngine(): ?string
    {
        $default = (string) config('database.default', '');

        if ($default === '') {
            return null;
        }

        $driver = config("database.connections.{$default}.driver");

        return is_string($driver) ? $driver : $default;
    }

    /**
     * @param  array<string, array{version: string|null, dev: bool}>  $installed
     */
    private function testFramework(array $installed): ?string
    {
        if (isset($installed['pestphp/pest'])) {
            return 'pest';
        }

        if (isset($installed['phpunit/phpunit'])) {
            return 'phpunit';
        }

        if ($this->files->exists(base_path('tests'))) {
            return 'detected-test-directory';
        }

        return null;
    }

    /**
     * @return array<string, bool|string>
     */
    private function frontendSignals(): array
    {
        $signals = [];

        if (class_exists(Livewire::class)) {
            $signals['livewire'] = 'detected';
        }

        if (class_exists(FilamentServiceProvider::class)) {
            $signals['filament'] = 'detected';
        }

        if ($this->files->exists(base_path('node_modules'))) {
            $package = base_path('package.json');

            if ($this->files->exists($package)) {
                $json = json_decode((string) $this->files->get($package), true);

                $deps = array_merge(
                    (array) ($json['dependencies'] ?? []),
                    (array) ($json['devDependencies'] ?? []),
                );

                foreach (['@inertiajs/vue3', '@inertiajs/react', '@inertiajs/svelte'] as $inertia) {
                    if (isset($deps[$inertia])) {
                        $signals['inertia'] = $inertia;

                        break;
                    }
                }

                if (isset($deps['tailwindcss'])) {
                    $signals['tailwind'] = true;
                }
            }
        }

        return $signals;
    }

    /**
     * @return array<string, bool>
     */
    private function architectureSignals(): array
    {
        return [
            'api' => $this->files->exists(base_path('routes/api.php')) || $this->files->exists(base_path('routes/api')),
            'console' => $this->files->exists(base_path('routes/console.php')),
            'websocket_channels' => $this->files->exists(base_path('routes/channels.php')),
            'queued_jobs' => $this->files->exists(app_path('Jobs')),
            'policies' => $this->files->exists(app_path('Policies')),
            'events' => $this->files->exists(app_path('Events')) || $this->files->exists(app_path('Listeners')),
            'observers' => $this->files->exists(app_path('Observers')),
            'console_commands' => $this->files->exists(app_path('Console/Commands')),
            'service_providers' => $this->files->exists(app_path('Providers')),
            'middleware' => $this->files->exists(app_path('Http/Middleware')),
            'notifications' => $this->files->exists(app_path('Notifications')),
            'mail' => $this->files->exists(app_path('Mail')),
        ];
    }

    /**
     * @return array<string, int|bool>
     */
    private function sourceLayout(): array
    {
        return [
            'app_files' => $this->countFiles(app_path()),
            'migrations' => $this->countFiles(database_path('migrations')),
            'routes_files' => $this->countFiles(base_path('routes')),
            'config_files' => $this->countFiles(config_path()),
            'test_files' => $this->countFiles(base_path('tests')),
            'has_factories' => $this->files->exists(database_path('factories')),
            'has_seeders' => $this->files->exists(database_path('seeders')),
        ];
    }

    /**
     * @return array<string, array{version: string|null, dev: bool}>
     */
    private function packages(): array
    {
        $packages = [];

        foreach (InstalledVersions::getInstalledPackages() as $package) {
            $packages[$package] = [
                'version' => InstalledVersions::getPrettyVersion($package),
                'dev' => (bool) InstalledVersions::isInstalled($package, includeDevRequirements: true),
            ];
        }

        ksort($packages);

        return $packages;
    }

    private function countFiles(string $path): int
    {
        if (! $this->files->isDirectory($path)) {
            return 0;
        }

        return count($this->files->allFiles($path));
    }

    private function relative(string $path): string
    {
        $base = str_replace('\\', '/', base_path());

        return str_replace('\\', '/', $path) === $base
            ? '.'
            : ltrim(str_replace('\\', '/', substr($path, strlen($base))), '/');
    }
}
