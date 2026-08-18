<?php

declare(strict_types=1);

namespace LaravelAuditor\Context\Collectors;

use Composer\InstalledVersions;
use Filament\FilamentServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use LaravelAuditor\Context\ContextCollector;
use LaravelAuditor\Support\ApplicationPaths;
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
        private readonly ApplicationPaths $paths,
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
            'laravel_version' => InstalledVersions::getPrettyVersion('laravel/framework'),
            'laravel_major_version' => $this->laravelMajorVersion(),
            'database' => $this->databaseEngine(),
            'test_framework' => $this->testFramework($installed),
            'frontend' => $this->frontendSignals(),
            'architecture_signals' => $this->architectureSignals(),
            'source_layout' => $this->sourceLayout(),
            'ecosystem' => $this->ecosystem($installed),
            'packages' => $this->notablePackages($installed),
            'paths' => [
                'app' => $this->relative($this->paths->directories()[0] ?? app_path()),
                'config' => $this->relative(config_path()),
                'database' => $this->relative($this->paths->siblings('database')[0] ?? database_path()),
                'routes' => $this->relative($this->paths->siblings('routes')[0] ?? base_path('routes')),
                'resources' => $this->relative(resource_path()),
                'tests' => $this->relative($this->paths->siblings('tests')[0] ?? base_path('tests')),
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

        if ($this->paths->siblings('tests') !== []) {
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
            'api' => $this->paths->siblings('routes/api.php') !== [] || $this->paths->siblings('routes/api') !== [],
            'console' => $this->paths->siblings('routes/console.php') !== [],
            'websocket_channels' => $this->paths->siblings('routes/channels.php') !== [],
            'queued_jobs' => $this->paths->has('Jobs'),
            'policies' => $this->paths->has('Policies'),
            'events' => $this->paths->has('Events') || $this->paths->has('Listeners'),
            'observers' => $this->paths->has('Observers'),
            'console_commands' => $this->paths->has('Console/Commands'),
            'service_providers' => $this->paths->has('Providers'),
            'middleware' => $this->paths->has('Http/Middleware'),
            'notifications' => $this->paths->has('Notifications'),
            'mail' => $this->paths->has('Mail'),
        ];
    }

    /**
     * @return array<string, int|bool>
     */
    private function sourceLayout(): array
    {
        $migrationCount = 0;

        foreach ($this->paths->siblings('database/migrations') as $directory) {
            if ($this->files->isDirectory($directory)) {
                $migrationCount += $this->countFiles($directory);
            }
        }

        $routeCount = 0;

        foreach ($this->paths->siblings('routes') as $directory) {
            if ($this->files->isDirectory($directory)) {
                $routeCount += $this->countFiles($directory);
            }
        }

        $testCount = 0;

        foreach ($this->paths->siblings('tests') as $directory) {
            if ($this->files->isDirectory($directory)) {
                $testCount += $this->countFiles($directory);
            }
        }

        return [
            'app_files' => $this->paths->fileCount(),
            'migrations' => $migrationCount,
            'routes_files' => $routeCount,
            'config_files' => $this->countFiles(config_path()),
            'test_files' => $testCount,
            'has_factories' => $this->paths->siblings('database/factories') !== [],
            'has_seeders' => $this->paths->siblings('database/seeders') !== [],
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
                'dev' => ! InstalledVersions::isInstalled($package, includeDevRequirements: false),
            ];
        }

        ksort($packages);

        return $packages;
    }

    /**
     * @param  array<string, array{version: string|null, dev: bool}>  $installed
     * @return array<string, bool>
     */
    private function ecosystem(array $installed): array
    {
        return [
            'livewire' => isset($installed['livewire/livewire']) || class_exists(Livewire::class),
            'filament' => isset($installed['filament/filament']) || class_exists(FilamentServiceProvider::class),
            'inertia' => isset($installed['inertiajs/inertia-laravel']),
            'pest' => isset($installed['pestphp/pest']),
            'phpunit' => isset($installed['phpunit/phpunit']),
            'tailwind' => (bool) ($this->frontendSignals()['tailwind'] ?? false),
            'queues' => (bool) ($this->architectureSignals()['queued_jobs'] ?? false),
            'boost' => isset($installed['laravel/boost']),
        ];
    }

    /**
     * @param  array<string, array{version: string|null, dev: bool}>  $installed
     * @return array<string, array{version: string|null, dev: bool}>
     */
    private function notablePackages(array $installed): array
    {
        $notable = [
            'laravel/framework',
            'laravel/boost',
            'laravel/sanctum',
            'laravel/passport',
            'laravel/horizon',
            'laravel/octane',
            'laravel/scout',
            'laravel/nova',
            'laravel/reverb',
            'livewire/livewire',
            'filament/filament',
            'inertiajs/inertia-laravel',
            'pestphp/pest',
            'phpunit/phpunit',
        ];

        $packages = [];

        foreach ($notable as $package) {
            if (isset($installed[$package])) {
                $packages[$package] = $installed[$package];
            }
        }

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
        $base = str_replace('\\', '/', rtrim(base_path(), '/\\'));
        $path = str_replace('\\', '/', $path);

        if ($path === $base) {
            return '.';
        }

        if (str_starts_with($path, $base.'/')) {
            return substr($path, strlen($base) + 1);
        }

        return $path;
    }
}
