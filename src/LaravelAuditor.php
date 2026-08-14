<?php

declare(strict_types=1);

namespace LaravelAuditor;

use LaravelAuditor\Audit\Rules\RuleRegistry;
use LaravelAuditor\Context\ContextRegistry;
use LaravelAuditor\Context\ProjectContext;

/**
 * Primary entry point for Laravel Auditor services.
 */
final class LaravelAuditor
{
    public function __construct(
        private readonly RuleRegistry $rules,
        private readonly ContextRegistry $context,
        private readonly ProjectContext $project,
    ) {}

    public function rules(): RuleRegistry
    {
        return $this->rules;
    }

    public function context(): ContextRegistry
    {
        return $this->context;
    }

    public function project(): ProjectContext
    {
        return $this->project;
    }

    /**
     * Collect structured context from a named collector.
     *
     * @return array<string, mixed>
     */
    public function collect(string $name): array
    {
        return $this->context->get($name)->collect();
    }
}
