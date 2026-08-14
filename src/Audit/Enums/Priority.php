<?php

declare(strict_types=1);

namespace LaravelAuditor\Audit\Enums;

use LaravelAuditor\Audit\Findings\Finding;

/**
 * Coordinator priority tiers for a validated audit finding.
 *
 * Inspired by a bounded, read-only subsystem audit: P0 is reachable
 * correctness/security/data-loss, P3 is intentionally small.
 */
enum Priority: string
{
    case P0 = 'p0';

    case P1 = 'p1';

    case P2 = 'p2';

    case P3 = 'p3';

    public function label(): string
    {
        return match ($this) {
            self::P0 => 'P0 - correctness, security, or data-loss risk',
            self::P1 => 'P1 - concrete correctness or high-leverage contract work',
            self::P2 => 'P2 - material invariant improvements with narrower impact',
            self::P3 => 'P3 - lower-impact telemetry, diagnostics, or maintainability',
        };
    }

    /**
     * The longer coordinator definition for this tier.
     */
    public function description(): string
    {
        return match ($this) {
            self::P0 => 'Reachable wrong-record, lost-update, authorization, durable-state, or permanently incomplete-operation risks.',
            self::P1 => 'Concrete boundary failures and high-leverage ownership fixes with less immediate damage or greater migration cost.',
            self::P2 => 'Useful invariant improvements whose observed impact is narrower or whose migration is sensitive.',
            self::P3 => 'Intentionally small after materiality review.',
        };
    }

    public function weight(): int
    {
        return match ($this) {
            self::P0 => 4,
            self::P1 => 3,
            self::P2 => 2,
            self::P3 => 1,
        };
    }

    public static function for(Finding $finding): self
    {
        $explicit = $finding->metadata['priority'] ?? null;

        if (is_string($explicit)) {
            $resolved = self::tryFrom(strtolower($explicit));

            if ($resolved !== null) {
                return $resolved;
            }
        }

        if ($finding->severity === Severity::Critical) {
            return self::P0;
        }

        if ($finding->severity === Severity::High && in_array($finding->domain, [AuditDomain::Security, AuditDomain::Database], true)) {
            return self::P0;
        }

        if ($finding->severity === Severity::High) {
            return self::P1;
        }

        if ($finding->severity === Severity::Medium && $finding->domain === AuditDomain::Security) {
            return self::P1;
        }

        if ($finding->severity === Severity::Medium) {
            return self::P2;
        }

        return self::P3;
    }
}
