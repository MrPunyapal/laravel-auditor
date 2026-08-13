<?php

declare(strict_types=1);

namespace LaravelAuditor\Audit\Domains;

use LaravelAuditor\Audit\Enums\AuditDomain;

/**
 * Central registry of the audit domains the package can report on.
 *
 * V1 ships the six core domains. The registry keeps the domain set explicit
 * and easy to extend with future domains without touching the core model.
 */
final class DomainRegistry
{
    /**
     * @var array<string, array{label: string, description: string}>
     */
    private array $domains;

    /**
     * @param  array<string, array{label: string, description: string}>  $domains
     */
    public function __construct(array $domains = [])
    {
        $this->domains = $domains === [] ? $this->defaultDomains() : $domains;
    }

    /**
     * @return array<string, array{label: string, description: string}>
     */
    public function all(): array
    {
        return $this->domains;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->domains);
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
