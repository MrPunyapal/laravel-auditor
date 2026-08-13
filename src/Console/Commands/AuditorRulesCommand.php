<?php

declare(strict_types=1);

namespace LaravelAuditor\Console\Commands;

use Illuminate\Console\Command;
use LaravelAuditor\Audit\Enums\AuditDomain;
use LaravelAuditor\Audit\Rules\RuleDefinition;
use LaravelAuditor\Audit\Rules\RuleRegistry;
use ValueError;

/**
 * Lists the available audit rules with their stable metadata.
 */
class AuditorRulesCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'auditor:rules
        {--domain= : Filter rules by domain (security, performance, architecture, database, testing, conventions)}
        {--json : Output rules as JSON}';

    /**
     * The command description.
     */
    protected $description = 'List the Laravel Auditor audit rules.';

    public function __construct(
        private readonly RuleRegistry $rules,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $domain = $this->option('domain');
        $rules = $this->rules->list();

        if (is_string($domain) && $domain !== '') {
            $domain = strtolower($domain);

            try {
                $enum = AuditDomain::from($domain);
            } catch (ValueError) {
                $this->components->error("Unknown domain [{$domain}].");

                return self::FAILURE;
            }

            $rules = array_values(array_filter(
                $rules,
                static fn (RuleDefinition $rule): bool => $rule->domain === $enum,
            ));
        }

        if ($this->option('json')) {
            $this->line((string) json_encode(
                array_map(static fn (RuleDefinition $rule): array => $rule->toArray(), $rules),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ));

            return self::SUCCESS;
        }

        if ($rules === []) {
            $this->components->info('No rules match the given criteria.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Domain', 'Severity', 'Confidence'],
            array_map(
                static fn (RuleDefinition $rule): array => [
                    $rule->id,
                    $rule->name,
                    $rule->domain->value,
                    $rule->severity->value,
                    $rule->confidence->value,
                ],
                $rules,
            ),
        );

        return self::SUCCESS;
    }
}
