<?php

declare(strict_types=1);

namespace LaravelAuditor\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use LaravelAuditor\Support\Agents\Agent;
use LaravelAuditor\Support\Agents\AgentDetector;
use LaravelAuditor\Support\Agents\AgentRegistry;
use LaravelAuditor\Support\Agents\InstallResult;
use LaravelAuditor\Support\Agents\McpConfigWriter;
use LaravelAuditor\Support\ApplicationPaths;
use LaravelAuditor\Support\BoostDetector;
use LaravelAuditor\Support\ResourcesTarget;

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
        {--agents=* : Agents to configure (built-in keys, or names from laravel-auditor.custom_agents)}';

    /**
     * The command description.
     */
    protected $description = 'Install Laravel Auditor resources into the application.';

    public function __construct(
        private readonly Filesystem $files,
        private readonly BoostDetector $boost,
        private readonly ApplicationPaths $paths,
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

        $result = new InstallResult;
        $agents = collect();

        if ($this->boost->isInstalled()) {
            $this->prepareBoostResources($dryRun, $force, $result);
        } else {
            $agents = $this->resolveAgents();

            $this->prepareStandaloneResources($dryRun, $force, $result);

            foreach ($agents as $agent) {
                $this->writeAdapter($this->guidelinesPath($agent), $dryRun, $force, $result);
                $this->copySkills($agent, $dryRun, $force, $result);
            }

            $this->prepareMcp($agents, $dryRun, $force, $result);

            $this->renderAgents($agents);
        }

        $this->components->twoColumnDetail('Configuration', config_path('laravel-auditor.php'));

        if ($this->shouldPublishConfig($dryRun)) {
            $this->publishConfig($dryRun, $result);
        }

        $this->renderSummary($dryRun, $result);

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

    private function prepareBoostResources(bool $dryRun, bool $force, InstallResult $result): void
    {
        // With Boost, the package ships resources under resources/boost which
        // Boost consumes directly. Nothing needs to be copied into the app.
        $this->components->twoColumnDetail('Boost guidelines', 'Provided by package (resources/boost/guidelines)');
        $this->components->twoColumnDetail('Boost skills', 'Provided by package (resources/boost/skills)');
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
            return $this->resolvedAgents($explicit);
        }

        $names = $this->input->isInteractive()
            ? $this->promptForAgents()
            : $this->defaultAgents();

        return $this->resolvedAgents($names);
    }

    /**
     * @param  list<string>  $names
     * @return Collection<int, Agent>
     */
    private function resolvedAgents(array $names): Collection
    {
        $resolved = AgentRegistry::resolve($names);
        $known = $resolved->map(fn (Agent $agent): string => $agent->name)->all();
        $unknown = array_values(array_unique(array_diff($names, $known)));

        if ($unknown !== []) {
            $this->components->warn('Unknown agent(s): '.implode(', ', $unknown).'. See custom_agents.');
        }

        return $resolved;
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

    private function prepareStandaloneResources(bool $dryRun, bool $force, InstallResult $result): void
    {
        $target = $this->standaloneTarget();

        if (! $dryRun) {
            $this->files->ensureDirectoryExists($target);
        }

        $this->components->twoColumnDetail('Agent resources', $this->paths->relativeToBase($target));

        foreach ($this->sourceResourceGroups() as $group => $source) {
            $dest = $target.DIRECTORY_SEPARATOR.$group;

            if (! $dryRun) {
                $this->files->ensureDirectoryExists($dest);
            }

            $this->copySkillTree($source, $dest, $dryRun, $force, $result);

            foreach (['*.md', '*.json'] as $pattern) {
                foreach ($this->files->glob(rtrim($source, '/\\').DIRECTORY_SEPARATOR.$pattern) as $file) {
                    $to = $dest.DIRECTORY_SEPARATOR.basename($file);

                    if ($this->files->exists($to) && ! $force) {
                        $result->updated[] = $this->paths->relativeToBase($to);

                        continue;
                    }

                    if (! $dryRun) {
                        $this->files->copy($file, $to);
                    }

                    $result->created[] = $this->paths->relativeToBase($to);
                }
            }
        }
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

    private function copySkills(Agent $agent, bool $dryRun, bool $force, InstallResult $result): void
    {
        $source = __DIR__.'/../../../resources/auditor/skills';
        $dest = base_path($agent->skillsPath);

        if (! $dryRun) {
            $this->files->ensureDirectoryExists($dest);
        }

        $this->copySkillTree($source, $dest, $dryRun, $force, $result);
    }

    /**
     * Copy every `SKILL.md` skill tree from one directory to another.
     */
    private function copySkillTree(string $source, string $dest, bool $dryRun, bool $force, InstallResult $result): void
    {
        foreach ($this->files->allDirectories($source) as $skillDir) {
            $relative = ltrim(str_replace('\\', '/', substr($skillDir, strlen($source))), '/');

            if (! $this->files->exists($skillDir.DIRECTORY_SEPARATOR.'SKILL.md')) {
                continue;
            }

            $to = $dest.DIRECTORY_SEPARATOR.$relative;

            if ($this->files->exists($to) && ! $force) {
                $result->updated[] = $this->paths->relativeToBase($to.DIRECTORY_SEPARATOR.'SKILL.md');

                continue;
            }

            if (! $dryRun) {
                $this->files->copyDirectory($skillDir, $to);
            }

            $result->created[] = $this->paths->relativeToBase($to.DIRECTORY_SEPARATOR.'SKILL.md');
        }
    }

    /**
     * @param  Collection<int, Agent>  $agents
     */
    private function prepareMcp(Collection $agents, bool $dryRun, bool $force, InstallResult $result): void
    {
        if ($agents->isEmpty()) {
            return;
        }

        $writer = new McpConfigWriter($this->files, $this->paths);

        foreach ($agents as $agent) {
            if (! $agent->supportsMcp()) {
                continue;
            }

            $this->components->twoColumnDetail('MCP server', 'Registering laravel-auditor for '.$agent->displayName);

            $writer->write($agent, $dryRun, $force, $result);
        }
    }

    private function writeAdapter(string $path, bool $dryRun, bool $force, InstallResult $result): void
    {
        $block = $this->adapterContents();

        if (! $this->files->exists($path)) {
            if (! $dryRun) {
                $this->files->ensureDirectoryExists(dirname($path));
                $this->files->put($path, $block);
            }

            $result->created[] = $this->paths->relativeToBase($path);

            return;
        }

        $existing = $this->files->get($path);

        if (str_contains($existing, '<!-- laravel-auditor -->')) {
            if (! $force) {
                $result->updated[] = $this->paths->relativeToBase($path);

                return;
            }

            $replaced = preg_replace(
                '/<!-- laravel-auditor -->.*?<!-- \/laravel-auditor -->/s',
                trim($block),
                $existing,
            );

            if (! $dryRun) {
                $this->files->put($path, is_string($replaced) ? $replaced : $existing);
            }

            $result->updated[] = $this->paths->relativeToBase($path);

            return;
        }

        if (! $force) {
            $this->components->twoColumnDetail($this->paths->relativeToBase($path), 'left unchanged (user-owned)');

            return;
        }

        if (! $dryRun) {
            $this->files->put($path, rtrim($existing).PHP_EOL.PHP_EOL.$block);
        }

        $result->updated[] = $this->paths->relativeToBase($path);
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

    private function publishConfig(bool $dryRun, InstallResult $result): void
    {
        $target = config_path('laravel-auditor.php');

        if ($this->files->exists($target)) {
            $result->updated[] = $this->paths->relativeToBase($target);

            return;
        }

        if (! $dryRun) {
            $this->files->copy(__DIR__.'/../../../config/laravel-auditor.php', $target);
        }

        $result->created[] = $this->paths->relativeToBase($target);
    }

    private function renderSummary(bool $dryRun, InstallResult $result): void
    {
        if ($dryRun) {
            $this->components->info('Dry run: no files were written.');
        }

        $this->newLine();

        $this->components->info('Summary');

        if ($result->created !== []) {
            $this->components->twoColumnDetail('Created', count($result->created).' file(s)');
            foreach ($result->created as $path) {
                $this->line('  <info>+</info> '.$path);
            }
        }

        if ($result->updated !== []) {
            $this->components->twoColumnDetail('Up to date', count($result->updated).' file(s)');
            foreach ($result->updated as $path) {
                $this->line('  <comment>=</comment> '.$path);
            }
        }

        if ($result->skipped !== []) {
            $this->components->twoColumnDetail('Skipped', count($result->skipped).' file(s)');
            foreach ($result->skipped as $path) {
                $this->line('  <error>-</error> '.$path);
            }
        }

        if ($result->isEmpty()) {
            $this->line('  Nothing to do.');
        }
    }

    private function resourcesTarget(): string
    {
        return ResourcesTarget::resolve();
    }
}
