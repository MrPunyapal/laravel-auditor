<?php

declare(strict_types=1);

namespace LaravelAuditor\Audit\Domains;

use LaravelAuditor\Audit\Enums\AuditDomain;

/**
 * Central registry of the audit domains the package can report on.
 *
 * V1 ships the six core domains. The set is closed: findings, rules, and
 * renderers all work with the `AuditDomain` enum, so the registry always
 * reflects the enum rather than an injectable map.
 */
final class DomainRegistry
{
    /**
     * @return array<string, array{label: string, description: string}>
     */
    public function all(): array
    {
        return $this->defaultDomains();
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    /**
     * @return list<AuditDomain>
     */
    public function core(): array
    {
        return AuditDomain::core();
    }

    /**
     * @return array<string, array{label: string, description: string}>
     */
    private function defaultDomains(): array
    {
        $domains = [];

        foreach (AuditDomain::core() as $domain) {
            $domains[$domain->value] = [
                'label' => $domain->label(),
                'description' => $domain->description(),
            ];
        }

        return $domains;
    }
}
