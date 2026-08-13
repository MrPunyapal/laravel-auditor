<?php

declare(strict_types=1);

namespace LaravelAuditor\Audit\Rules;

use Illuminate\Filesystem\Filesystem;
use LaravelAuditor\Audit\Enums\AuditDomain;
use RuntimeException;

/**
 * Discovers and validates the package's built-in audit rules.
 *
 * Rules live as PHP files under resources/auditor/rules. Each file returns a
 * list of rule metadata arrays; the registry hydrates them into immutable
 * RuleDefinition value objects and validates stable metadata up front.
 */
final class RuleRegistry
{
    /**
     * @var array<string, RuleDefinition>|null
     */
    private ?array $rules = null;

    /**
     * @param  list<string>  $paths  Directories containing rule definition files.
     */
    public function __construct(
        private readonly Filesystem $files,
        private readonly array $paths = [],
    ) {}

    /**
     * @return array<string, RuleDefinition> Keyed by rule ID.
     */
    public function all(): array
    {
        return $this->load();
    }

    public function find(string $id): ?RuleDefinition
    {
        return $this->load()[$id] ?? null;
    }

    /**
     * @return list<RuleDefinition>
     */
    public function forDomain(AuditDomain $domain): array
    {
        return array_values(array_filter(
            $this->load(),
            static fn (RuleDefinition $rule): bool => $rule->domain === $domain,
        ));
    }

    /**
     * @return list<RuleDefinition>
     */
    public function list(): array
    {
        return array_values($this->load());
    }

    /**
     * @return array<string, int> Rule counts keyed by domain.
     */
    public function countsByDomain(): array
    {
        $counts = [];

        foreach ($this->load() as $rule) {
            $counts[$rule->domain->value] = ($counts[$rule->domain->value] ?? 0) + 1;
        }

        return $counts;
    }

    public function count(): int
    {
        return count($this->load());
    }

    /**
     * @return array<string, RuleDefinition>
     */
    private function load(): array
    {
        if ($this->rules !== null) {
            return $this->rules;
        }

        $this->rules = [];

        foreach ($this->paths as $path) {
            foreach ($this->files->glob(rtrim($path, '/\\').DIRECTORY_SEPARATOR.'*.php') as $file) {
                $this->rules = $this->mergeRules($this->rules, $this->loadFile($file));
            }
        }

        return $this->rules;
    }

    /**
     * @param  array<string, RuleDefinition>  $rules
     * @param  list<RuleDefinition>  $incoming
     * @return array<string, RuleDefinition>
     */
    private function mergeRules(array $rules, array $incoming): array
    {
        foreach ($incoming as $rule) {
            if (isset($rules[$rule->id])) {
                throw new RuntimeException("Duplicate audit rule ID [{$rule->id}].");
            }

            $rules[$rule->id] = $rule;
        }

        return $rules;
    }

    /**
     * @return list<RuleDefinition>
     */
    private function loadFile(string $file): array
    {
        $data = require $file;

        if (! is_array($data)) {
            throw new RuntimeException("Audit rule file [{$file}] must return an array.");
        }

        $rules = [];

        foreach ($data as $item) {
            if (! is_array($item)) {
                throw new RuntimeException("Audit rule file [{$file}] must contain only rule arrays.");
            }

            $rules[] = $this->hydrate($item, $file);
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function hydrate(array $data, string $file): RuleDefinition
    {
        $required = ['id', 'name', 'domain', 'severity', 'description'];

        foreach ($required as $key) {
            if (empty($data[$key])) {
                throw new RuntimeException("Audit rule in [{$file}] is missing required key [{$key}].");
            }
        }

        return RuleDefinition::fromArray($data);
    }
}
