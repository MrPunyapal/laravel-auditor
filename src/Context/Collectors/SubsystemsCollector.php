<?php

declare(strict_types=1);

namespace LaravelAuditor\Context\Collectors;

use Illuminate\Filesystem\Filesystem;
use LaravelAuditor\Context\ContextCollector;
use LaravelAuditor\Support\ApplicationPaths;
use SplFileInfo;

/**
 * Inventories identifiable Laravel subsystems for a bounded, read-only audit.
 *
 * This is the coverage contract: one row per ownership boundary, not a
 * catch-all "the app" bucket.
 */
final class SubsystemsCollector implements ContextCollector
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly ApplicationPaths $paths,
    ) {}

    public function name(): string
    {
        return 'subsystems';
    }

    public function description(): string
    {
        return 'Inventory Laravel subsystems with ownership boundaries for a bounded DSA-style audit.';
    }

    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $subsystems = array_values(array_filter([
            $this->row('HTTP', 'HTTP / routing', ['Http', 'Http/Controllers'], 'routes/, app/Http', ['routes']),
            $this->row('MDL', 'Eloquent models', ['Models'], 'app/Models', ['models']),
            $this->row('AUTH', 'Authorization', ['Policies', 'Http/Middleware'], 'app/Policies, gates, auth middleware', ['policies_authorization']),
            $this->row('DB', 'Database / migrations', [], 'database/migrations and schema', ['migrations', 'database_schema'], database_path('migrations')),
            $this->row('JOB', 'Jobs / queues', ['Jobs'], 'app/Jobs, queue config', ['jobs_events_schedules']),
            $this->row('EVT', 'Events / listeners', ['Events', 'Listeners'], 'app/Events, app/Listeners', ['jobs_events_schedules']),
            $this->row('CMD', 'Console / schedule', ['Console/Commands'], 'app/Console, routes/console.php', ['jobs_events_schedules']),
            $this->row('MAIL', 'Mail / notifications', ['Mail', 'Notifications'], 'app/Mail, app/Notifications', []),
            $this->row('TST', 'Tests', [], 'tests/', ['tests'], base_path('tests')),
            $this->row('CFG', 'Configuration', [], 'config/', ['configuration'], config_path()),
            $this->row('DEP', 'Dependencies', [], 'composer.json / installed packages', ['dependencies'], base_path('composer.json')),
        ]));

        return [
            'count' => count($subsystems),
            'coverage_contract' => 'Each row is an exclusive ownership boundary. Do not treat this list as complete without inspecting leftover app/ directories.',
            'subsystems' => $subsystems,
        ];
    }

    /**
     * @param  list<string>  $relativeDirs
     * @param  list<string>  $collectors
     * @return array<string, mixed>|null
     */
    private function row(
        string $id,
        string $name,
        array $relativeDirs,
        string $boundary,
        array $collectors,
        ?string $fallbackPath = null,
    ): ?array {
        $files = [];

        foreach ($relativeDirs as $relative) {
            foreach ($this->paths->directories($relative) as $directory) {
                $files = array_merge($files, $this->sampleFiles($directory));
            }
        }

        if ($fallbackPath !== null && ($this->files->isDirectory($fallbackPath) || $this->files->exists($fallbackPath))) {
            if ($this->files->isDirectory($fallbackPath)) {
                $files = array_merge($files, $this->sampleFiles($fallbackPath));
            } else {
                $files[] = $this->relative($fallbackPath);
            }
        }

        $files = array_values(array_unique($files));

        if ($files === [] && $fallbackPath === null && $relativeDirs !== []) {
            return null;
        }

        return [
            'id' => $id,
            'name' => $name,
            'boundary' => $boundary,
            'files' => array_slice($files, 0, 20),
            'collectors' => $collectors,
            'status' => 'queued',
        ];
    }

    /**
     * @return list<string>
     */
    private function sampleFiles(string $directory): array
    {
        if (! $this->files->isDirectory($directory)) {
            return [];
        }

        return array_values(array_map(
            fn (SplFileInfo $file): string => $this->relative($file->getPathname()),
            array_slice($this->files->allFiles($directory), 0, 20),
        ));
    }

    private function relative(string $path): string
    {
        $base = str_replace('\\', '/', base_path());
        $path = str_replace('\\', '/', $path);

        return $path === $base ? '.' : ltrim(substr($path, strlen($base)), '/');
    }
}
