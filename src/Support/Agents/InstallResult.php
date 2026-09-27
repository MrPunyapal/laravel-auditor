<?php

declare(strict_types=1);

namespace LaravelAuditor\Support\Agents;

/**
 * Accumulates installer file accounting across install steps.
 *
 * Replaces the `created` / `updated` / `skipped` tuple threading so a step
 * cannot silently drop the accounting of a previous one.
 */
final class InstallResult
{
    /**
     * @var list<string>
     */
    public array $created = [];

    /**
     * @var list<string>
     */
    public array $updated = [];

    /**
     * @var list<string>
     */
    public array $skipped = [];

    public function isEmpty(): bool
    {
        return $this->created === [] && $this->updated === [] && $this->skipped === [];
    }
}
