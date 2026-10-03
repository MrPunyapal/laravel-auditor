<?php

declare(strict_types=1);

namespace LaravelAuditor\Context\Collectors;

use LaravelAuditor\Context\ContextCollector;
use LaravelAuditor\Support\ReviewScope;

/**
 * The audit boundary for work in progress.
 *
 * Returns the uncommitted files and the views, tests, and classes they
 * directly use. An agent reads `scope` and leaves the rest of the application
 * alone. Git is optional: a missing repository returns a reason instead of an
 * empty change set.
 */
final class ReviewScopeCollector implements ContextCollector
{
    public function __construct(private readonly ReviewScope $scope) {}

    public function name(): string
    {
        return 'review_scope';
    }

    public function description(): string
    {
        return 'Audit boundary for uncommitted work: the dirty files plus the views, tests, and classes they directly use. Read scope and do not inventory the rest of the application. available=false means git is unavailable, not that nothing changed. An empty changed list is a clean tree.';
    }

    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        return $this->scope->collect();
    }
}
