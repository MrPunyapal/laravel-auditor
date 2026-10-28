<?php

declare(strict_types=1);

namespace LaravelAuditor\Audit\Findings;

use LaravelAuditor\Audit\Enums\AuditDomain;
use LaravelAuditor\Audit\Enums\Confidence;
use LaravelAuditor\Audit\Enums\Severity;
use LaravelAuditor\Audit\Evidence\Evidence;
use LaravelAuditor\Audit\Evidence\EvidenceCollection;

/**
 * Converts Composer's abandoned-package notices into reviewable findings.
 */
final class ComposerAbandonedFindings
{
    /**
     * @param  array<string, mixed>  $audit
     * @return list<Finding>
     */
    public static function fromAudit(array $audit): array
    {
        $findings = [];

        foreach (($audit['abandoned'] ?? []) as $package => $replacement) {
            if (! is_string($package) || $package === '') {
                continue;
            }

            $replacement = is_string($replacement) && $replacement !== '' ? $replacement : null;

            $findings[] = new Finding(
                id: 'abandoned-'.substr(hash('sha256', $package), 0, 16),
                ruleId: 'AUD-DEP-002',
                title: 'Abandoned Composer package: '.$package,
                domain: AuditDomain::Conventions,
                severity: Severity::Low,
                confidence: Confidence::High,
                summary: "Composer reports [{$package}] as abandoned.",
                whyItMatters: 'Abandoned dependencies may stop receiving compatibility and security fixes. Their importance depends on how the application uses them.',
                evidence: EvidenceCollection::fromIterable([
                    Evidence::dependency($package, detail: 'Composer audit abandoned-package report'),
                ]),
                affectedResources: [],
                recommendation: $replacement !== null
                    ? 'Evaluate the suggested replacement ['.$replacement.'] and plan a compatible migration if the package is still used.'
                    : 'Determine whether the dependency is still needed and identify a maintained alternative where appropriate.',
                verificationNotes: 'Composer identifies this package as abandoned; its application usage and risk have not been verified.',
                metadata: [
                    'replacement_package' => $replacement,
                    'source' => 'composer_audit_abandoned',
                ],
            );
        }

        return $findings;
    }
}
