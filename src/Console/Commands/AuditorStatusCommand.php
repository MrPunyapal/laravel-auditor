<?php

declare(strict_types=1);

namespace LaravelAuditor\Console\Commands;

use Composer\InstalledVersions;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use LaravelAuditor\Audit\Domains\DomainRegistry;
use LaravelAuditor\Audit\Rules\RuleRegistry;
use LaravelAuditor\Context\ContextRegistry;
use LaravelAuditor\Support\BoostDetector;
use Throwable;

/**
 * Reports the installation and runtime status of Laravel Auditor.
 */
class AuditorStatusCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'auditor:status';

    /**
     * The command description.
     */
    protected $description = 'Show the status of Laravel Auditor in this application.';

    public function __construct(
        private readonly BoostDetector $boost,
        private readonly RuleRegistry $rules,
        private readonly DomainRegistry $domains,
        private readonly ContextRegistry $context,
        private readonly Filesystem $files,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->components->info('Laravel Auditor status');

        $this->components->twoColumnDetail('Installed', 'yes');
        $this->components->twoColumnDetail('Version', $this->auditorVersion() ?? 'unknown');
        $this->components->twoColumnDetail('Laravel', $this->laravelVersion() ?? 'unknown');
        $this->components->twoColumnDetail('PHP', PHP_VERSION);

        $this->newLine();
        $this->components->info('Integration');

        if ($this->boost->isInstalled()) {
            $this->components->twoColumnDetail('Laravel Boost', $this->boost->version() ?? 'installed');
            $this->components->twoColumnDetail('Mechanism', 'Third-party guidelines/skills via Boost');
        } else {
            $this->components->twoColumnDetail('Laravel Boost', 'not installed');
            $this->components->twoColumnDetail('Mechanism', 'Standalone installer / resources');
        }

        $this->components->twoColumnDetail(
            'Standalone resources',
            $this->files->isDirectory(base_path($this->resourcesTarget())) ? 'present' : 'not prepared (run `auditor:install`)',
        );

        $this->components->twoColumnDetail('Configuration', $this->files->exists(config_path('laravel-auditor.php')) ? 'published' : 'using package defaults');

        $this->newLine();
        $this->components->info('Audit domains');

        $domains = $this->domains->all();

        $this->table(['Domain', 'Label'], array_map(
            fn (string $key): array => [$key, $domains[$key]['label'] ?? $key],
            $this->domains->keys(),
        ));

        $this->newLine();
        $this->components->info('Rules');
        $this->components->twoColumnDetail('Total rules', (string) $this->rules->count());

        foreach ($this->rules->countsByDomain() as $domain => $count) {
            $this->components->twoColumnDetail($domain, (string) $count);
        }

        $this->newLine();
        $this->components->info('Context tools');
        $this->components->twoColumnDetail('Available tools', implode(', ', $this->context->names()));

        return self::SUCCESS;
    }

    private function laravelVersion(): ?string
    {
        try {
            return InstalledVersions::getPrettyVersion('laravel/framework');
        } catch (Throwable) {
            return null;
        }
    }

    private function auditorVersion(): ?string
    {
        try {
            return InstalledVersions::getPrettyVersion('mrpunyapal/laravel-auditor');
        } catch (Throwable) {
            return null;
        }
    }

    private function resourcesTarget(): string
    {
        $target = trim((string) config('laravel-auditor.resources_target', '.ai'), '/\\');

        return $target !== '' ? $target : '.ai';
    }
}
