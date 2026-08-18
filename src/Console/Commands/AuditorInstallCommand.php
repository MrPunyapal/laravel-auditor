<?php

declare(strict_types=1);

namespace LaravelAuditor\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use LaravelAuditor\Support\Agents\Agent;
use LaravelAuditor\Support\Agents\AgentDetector;
use LaravelAuditor\Support\Agents\AgentRegistry;
use LaravelAuditor\Support\Agents\McpConfigWriter;
use LaravelAuditor\Support\BoostDetector;

/**
 * Installs Laravel Auditor into the consuming application.
 *
 * Detects the Laravel context and whether Boost is installed, prepares the
 * agent-facing resources, and reports what was created. Idempotent and safe.
 *
 * When Boost is absent the standalone path asks which AI agents the project
 * uses (or resolves them from the `--agents` option, project detection, or
 * the `laravel-auditor.agents` config) and wires skills, guideline adapters,
 * and the MCP server only for the selected agents.
 */
class AuditorInstallCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'auditor:install
        {--force : Overwrite existing Auditor-owned resources}
        {--dry-run : Show what would be created without writing anything}
        {--agents=* : Agents to configure (opencode, claude_code, cursor, copilot, gemini, codex, junie, zed)}';

    /**
     * The command description.
     */
    protected $description = 'Install Laravel Auditor resources into the application.';

    public function __construct(
        private readonly Filesystem $files,
        private readonly BoostDetector $boost,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $this->components->info('Laravel Auditor installation');

        if (! $this->looksLikeLaravel()) {
            $this->components->error('This does not look like a Laravel application root.');

            return self::FAILURE;
        }

        if ($this->boost->isInstalled()) {
            $this->components->info('Laravel Boost detected.');
            $this->components->twoColumnDetail('Integration', 'Boost will consume Auditor resources on `boost:install` / `boost:update`');
        } else {
            $this->components->info('Laravel Boost not detected. Using the standalone setup path.');
        }

        $created = $updated = $skipped = [];
        $agents = collect();

        if ($this->boost->isInstalled()) {
            [$created, $updated] = $this->prepareBoostResources($dryRun, $force, $created, $updated);
        } else {
            $agents = $this->resolveAgents();

            [$created, $updated] = $this->prepareStandaloneResources($dryRun, $force, $created, $updated);

            foreach ($agents as $agent) {
                [$created, $updated] = $this->writeAdapter($this->guidelinesPath($agent), $dryRun, $force, $created, $updated);
                [$created, $updated] = $this->copySkills($agent, $dryRun, $force, $created, $updated);
            }

            [$created, $updated, $skipped] = $this->prepareMcp($agents, $dryRun, $force, $created, $updated, $skipped);

            $this->renderAgents($agents);
        }

        $this->components->twoColumnDetail('Configuration', config_path('laravel-auditor.php'));

        if ($this->shouldPublishConfig($dryRun)) {
            $this->publishConfig($dryRun, $created, $updated);
        }

        $this->renderSummary($dryRun, $created, $updated, $skipped);

        if ($this->boost->isInstalled()) {
            $this->components->info('Run `php artisan boost:install` (or `boost:update`) to expose Auditor resources to Boost.');
        } elseif ($agents->isEmpty()) {
            $this->components->info('No agents selected. Run again with `--agents` to wire skills and MCP for a specific tool.');
        }

        return self::SUCCESS;
    }

    private function looksLikeLaravel(): bool
    {
        return $this->files->exists(base_path('artisan'))
            || $this->files->isDirectory(base_path('app'));
    }

    /**
     * @param  list<string>  $created
     * @param  list<string>  $updated
     * @return array{list<string>, list<string>}
     */
    private function prepareBoostResources(bool $dryRun, bool $force, array $created, array $updated): array
    {
        // With Boost, the package ships resources under resources/boost which
        // Boost consumes directly. Nothing needs to be copied into the app.
        $this->components->twoColumnDetail('Boost guidelines', 'Provided by package (resources/boost/guidelines)');
        $this->components->twoColumnDetail('Boost skills', 'Provided by package (resources/boost/skills)');

        return [$created, $updated];
    }

    /**
     * Resolve which agents to wire in the standalone path.
     *
     * Priority: explicit `--agents` option, interactive selection (defaulting
     * to detected agents), the `laravel-auditor.agents` config, then project
     * detection. No agents are selected when none of those resolve.
     *
     * @return Collection<int, Agent>
     */
    private function resolveAgents(): Collection
    {
        $explicit = $this->agentsOption();

        if ($explicit !== []) {
            return AgentRegistry::resolve($explicit);
        }

        $names = $this->input->isInteractive()
            ? $this->promptForAgents()
            : $this->defaultAgents();

        return AgentRegistry::resolve($names);
    }

    /**
     * @return list<string>
     */
    private function agentsOption(): array
    {
        $names = [];

        foreach ((array) $this->option('agents') as $value) {
            if ($value === null) {
                continue;
            }

            foreach (preg_split('/[\s,]+/', trim($value)) ?: [] as $name) {
                if ($name !== '') {
                    $names[] = $name;
                }
            }
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    private function promptForAgents(): array
    {
        $options = collect(AgentRegistry::all())
            ->mapWithKeys(fn (Agent $agent): array => [$agent->name => $agent->displayName])
            ->all();

        $defaults = (new AgentDetector($this->files))->detect(base_path());

        $selected = $this->choice(
            question: 'Which AI agents would you like to configure?',
            choices: $options,
            default: implode(',', $defaults),
            attempts: null,
            multiple: true,
        );

        if (is_array($selected)) {
            return array_values($selected);
        }

        return preg_split('/[\s,]+/', (string) $selected) ?: [];
    }

    /**
     * @return list<string>
     */
    private function defaultAgents(): array
    {
        $configured = array_values(array_filter(array_map('strval', (array) config('laravel-auditor.agents', []))));

        if ($configured !== []) {
            return $configured;
        }

        return (new AgentDetector($this->files))->detect(base_path());
    }

    /**
     * @param  Collection<int, Agent>  $agents
     */
    private function renderAgents(Collection $agents): void
    {
        if ($agents->isEmpty()) {
            return;
        }

        $this->components->twoColumnDetail('Agents', $agents->map(fn (Agent $agent): string => $agent->displayName)->implode(', '));
    }

    /**
     * @param  list<string>  $created
     * @param  list<string>  $updated
     * @return array{list<string>, list<string>}
     */
    private function prepareStandaloneResources(bool $dryRun, bool $force, array $created, array $updated): array
    {
        $target = $this->standaloneTarget();

        if (! $dryRun) {
            $this->files->ensureDirectoryExists($target);
        }

        $this->components->twoColumnDetail('Agent resources', $this->relative($target));

        foreach ($this->sourceResourceGroups() as $group => $source) {
            $dest = $target.DIRECTORY_SEPARATOR.$group;

            if (! $dryRun) {
                $this->files->ensureDirectoryExists($dest);
            }

            foreach ($this->files->allDirectories($source) as $skillDir) {
                $relative = ltrim(str_replace('\\', '/', substr($skillDir, strlen($source))), '/');

                if (! $this->files->exists($skillDir.DIRECTORY_SEPARATOR.'SKILL.md')) {
                    continue;
                }

                $to = $dest.DIRECTORY_SEPARATOR.$relative;

                if ($this->files->exists($to) && ! $force) {
                    $updated[] = $this->relative($to.DIRECTORY_SEPARATOR.'SKILL.md');

                    continue;
                }

                if (! $dryRun) {
                    $this->files->copyDirectory($skillDir, $to);
                }

                $created[] = $this->relative($to.DIRECTORY_SEPARATOR.'SKILL.md');
            }

            foreach (['*.md', '*.json'] as $pattern) {
                foreach ($this->files->glob(rtrim($source, '/\\').DIRECTORY_SEPARATOR.$pattern) as $file) {
                    $to = $dest.DIRECTORY_SEPARATOR.basename($file);

                    if ($this->files->exists($to) && ! $force) {
                        $updated[] = $this->relative($to);

                        continue;
                    }

                    if (! $dryRun) {
                        $this->files->copy($file, $to);
                    }

                    $created[] = $this->relative($to);
                }
            }
        }

        return [$created, $updated];
    }

    /**
     * @return array<string, string>
     */
    private function sourceResourceGroups(): array
    {
        return [
            'skills' => __DIR__.'/../../../resources/auditor/skills',
            'guidelines' => __DIR__.'/../../../resources/auditor/guidelines',
            'schema' => __DIR__.'/../../../resources/auditor/schema',
            'examples' => __DIR__.'/../../../resources/auditor/examples',
            'mcp' => __DIR__.'/../../../resources/auditor/mcp',
        ];
    }

    private function guidelinesPath(Agent $agent): string
    {
        return base_path($agent->guidelinesPath);
    }

    /**
     * @param  list<string>  $created
     * @param  list<string>  $updated
     * @return array{list<string>, list<string>}
     */
    private function copySkills(Agent $agent, bool $dryRun, bool $force, array $created, array $updated): array
    {
        $source = __DIR__.'/../../../resources/auditor/skills';
        $dest = base_path($agent->skillsPath);

        if (! $dryRun) {
            $this->files->ensureDirectoryExists($dest);
        }

        foreach ($this->files->allDirectories($source) as $skillDir) {
            $relative = ltrim(str_replace('\\', '/', substr($skillDir, strlen($source))), '/');

            if (! $this->files->exists($skillDir.DIRECTORY_SEPARATOR.'SKILL.md')) {
                continue;
            }

            $to = $dest.DIRECTORY_SEPARATOR.$relative;

            if ($this->files->exists($to) && ! $force) {
                $updated[] = $this->relative($to.DIRECTORY_SEPARATOR.'SKILL.md');

                continue;
            }

            if (! $dryRun) {
                $this->files->copyDirectory($skillDir, $to);
            }

            $created[] = $this->relative($to.DIRECTORY_SEPARATOR.'SKILL.md');
        }

        return [$created, $updated];
    }

    /**
     * @param  Collection<int, Agent>  $agents
     * @param  list<string>  $created
     * @param  list<string>  $updated
     * @param  list<string>  $skipped
     * @return array{list<string>, list<string>, list<string>}
     */
    private function prepareMcp(Collection $agents, bool $dryRun, bool $force, array $created, array $updated, array $skipped): array
    {
        if ($agents->isEmpty()) {
            return [$created, $updated, $skipped];
        }

        $writer = new McpConfigWriter($this->files);

        foreach ($agents as $agent) {
            if (! $agent->supportsMcp()) {
                continue;
            }

            $this->components->twoColumnDetail('MCP server', 'Registering laravel-auditor for '.$agent->displayName);

            [$created, $updated, $skipped] = $writer->write($agent, $dryRun, $force, $created, $updated, $skipped);
        }

        return [$created, $updated, $skipped];
    }

    /**
     * @param  list<string>  $created
     * @param  list<string>  $updated
     * @return array{list<string>, list<string>}
     */
    private function writeAdapter(string $path, bool $dryRun, bool $force, array $created, array $updated): array
    {
        $block = $this->adapterContents();

        if (! $this->files->exists($path)) {
            if (! $dryRun) {
                $this->files->ensureDirectoryExists(dirname($path));
                $this->files->put($path, $block);
            }

            $created[] = $this->relative($path);

            return [$created, $updated];
        }

        $existing = $this->files->get($path);

        if (str_contains($existing, '<!-- laravel-auditor -->')) {
            if (! $force) {
                $updated[] = $this->relative($path);

                return [$created, $updated];
            }

            $replaced = preg_replace(
                '/<!-- laravel-auditor -->.*?<!-- \/laravel-auditor -->/s',
                trim($block),
                $existing,
            );

            if (! $dryRun) {
                $this->files->put($path, is_string($replaced) ? $replaced : $existing);
            }

            $updated[] = $this->relative($path);

            return [$created, $updated];
        }

        if (! $force) {
            $this->components->twoColumnDetail($this->relative($path), 'left unchanged (user-owned)');

            return [$created, $updated];
        }

        if (! $dryRun) {
            $this->files->put($path, rtrim($existing).PHP_EOL.PHP_EOL.$block);
        }

        $updated[] = $this->relative($path);

        return [$created, $updated];
    }

    private function adapterContents(): string
    {
        $target = $this->resourcesTarget();

        return <<<MARKDOWN
<!-- laravel-auditor -->
# Laravel Auditor

This project uses Laravel Auditor for evidence-based Laravel audits.

When asked to audit, review, or assess this application, use the `laravel-audit` skill in `{$target}/skills/laravel-audit` and follow `{$target}/guidelines/core.md`.

Do not modify application code during an audit. Prefer deterministic project facts from `php artisan auditor:status`, `php artisan auditor:rules`, and the Laravel Auditor MCP tools.
<!-- /laravel-auditor -->

MARKDOWN;
    }

    private function standaloneTarget(): string
    {
        return base_path($this->resourcesTarget());
    }

    private function shouldPublishConfig(bool $dryRun): bool
    {
        return $dryRun || ! $this->files->exists(config_path('laravel-auditor.php'));
    }

    /**
     * @param  list<string>  $created
     * @param  list<string>  $updated
     */
    private function publishConfig(bool $dryRun, array &$created, array &$updated): void
    {
        $target = config_path('laravel-auditor.php');

        if ($this->files->exists($target)) {
            $updated[] = $this->relative($target);

            return;
        }

        if (! $dryRun) {
            $this->files->copy(__DIR__.'/../../../config/laravel-auditor.php', $target);
        }

        $created[] = $this->relative($target);
    }

    /**
     * @param  list<string>  $created
     * @param  list<string>  $updated
     * @param  list<string>  $skipped
     */
    private function renderSummary(bool $dryRun, array $created, array $updated, array $skipped): void
    {
        if ($dryRun) {
            $this->components->info('Dry run: no files were written.');
        }

        $this->newLine();

        $this->components->info('Summary');

        if ($created !== []) {
            $this->components->twoColumnDetail('Created', count($created).' file(s)');
            foreach ($created as $path) {
                $this->line('  <info>+</info> '.$path);
            }
        }

        if ($updated !== []) {
            $this->components->twoColumnDetail('Up to date', count($updated).' file(s)');
            foreach ($updated as $path) {
                $this->line('  <comment>=</comment> '.$path);
            }
        }

        if ($skipped !== []) {
            $this->components->twoColumnDetail('Skipped', count($skipped).' file(s)');
            foreach ($skipped as $path) {
                $this->line('  <error>-</error> '.$path);
            }
        }

        if ($created === [] && $updated === [] && $skipped === []) {
            $this->line('  Nothing to do.');
        }
    }

    private function resourcesTarget(): string
    {
        $target = trim((string) config('laravel-auditor.resources_target', '.ai'), '/\\');

        return $target !== '' ? $target : '.ai';
    }

    private function relative(string $path): string
    {
        $base = str_replace('\\', '/', base_path());
        $path = str_replace('\\', '/', $path);

        return ltrim(substr($path, strlen($base)), '/');
    }
}
