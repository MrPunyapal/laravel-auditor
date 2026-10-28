<?php

declare(strict_types=1);

namespace LaravelAuditor\Console\Commands;

use Illuminate\Console\Command;
use LaravelAuditor\Audit\Findings\ComposerAdvisoryFindings;
use LaravelAuditor\Audit\Findings\ComposerAbandonedFindings;
use LaravelAuditor\Context\Collectors\DependenciesCollector;
use Throwable;

/**
 * Export deterministic Composer advisory findings without an AI agent.
 */
final class AuditorDependencyFindingsCommand extends Command
{
    protected $signature = 'auditor:dependency-findings
        {--include-abandoned : Also export Composer abandoned-package notices}
        {--output= : Write findings JSON to a file instead of stdout}';

    protected $description = 'Export known Composer vulnerabilities as Auditor findings.';

    public function __construct(
        private readonly DependenciesCollector $dependencies,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $data = $this->dependencies->collect();
        } catch (Throwable $e) {
            $this->components->error('Dependency audit failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $audit = $data['composer_audit'] ?? [];

        if (! is_array($audit) || ($audit['available'] ?? false) !== true) {
            $this->components->error('Composer audit unavailable: '.(is_array($audit) ? ($audit['reason'] ?? 'unknown reason') : 'invalid result'));

            return self::FAILURE;
        }

        if (($audit['complete'] ?? true) !== true) {
            $this->components->error('Composer audit incomplete: '.($audit['reason'] ?? 'some advisory repositories were unreachable'));

            return self::FAILURE;
        }

        $findings = ComposerAdvisoryFindings::fromAudit($audit);

        if ((bool) $this->option('include-abandoned')) {
            if (is_array($audit['abandoned'] ?? null) === false) {
                $this->components->error('Composer audit did not provide abandoned-package data.');

                return self::FAILURE;
            }

            foreach (ComposerAbandonedFindings::fromAudit($audit) as $finding) {
                $findings[] = $finding;
            }
        }
        $json = json_encode(['findings' => $findings], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
        $output = $this->option('output');

        if (! is_string($output) || $output === '') {
            $this->line($json);

            return self::SUCCESS;
        }

        if (@file_put_contents($output, $json) === false) {
            $this->components->error("Could not write findings to [{$output}].");

            return self::FAILURE;
        }

        $this->components->info(sprintf('Wrote %d Composer advisory finding(s) to [%s].', count($findings), $output));

        return self::SUCCESS;
    }
}
