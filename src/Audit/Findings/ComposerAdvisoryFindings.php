<?php

declare(strict_types=1);

namespace LaravelAuditor\Audit\Findings;

use LaravelAuditor\Audit\Enums\AuditDomain;
use LaravelAuditor\Audit\Enums\Confidence;
use LaravelAuditor\Audit\Enums\Severity;
use LaravelAuditor\Audit\Evidence\Evidence;
use LaravelAuditor\Audit\Evidence\EvidenceCollection;

/**
 * Converts verified Composer advisory matches into standard Auditor findings.
 *
 * This does not infer whether a vulnerable API is reachable in an application.
 */
final class ComposerAdvisoryFindings
{
    /**
     * @param  array<string, mixed>  $audit
     * @return list<Finding>
     */
    public static function fromAudit(array $audit): array
    {
        $findings = [];

        foreach (($audit['advisories'] ?? []) as $advisory) {
            if (! is_array($advisory)) {
                continue;
            }

            $package = self::string($advisory, 'package');

            if ($package === '') {
                continue;
            }

            $id = self::string($advisory, 'advisory_id');
            $cve = self::string($advisory, 'cve');
            $title = self::string($advisory, 'title');
            $affected = self::string($advisory, 'affected_versions');
            $link = self::string($advisory, 'link');

            $reference = $id !== '' ? $id : ($cve !== '' ? $cve : $title);
            $key = $package.'|'.$reference.'|'.$affected;
            $reportedSeverity = strtolower(self::string($advisory, 'severity'));
            $severity = Severity::tryFrom($reportedSeverity) ?? Severity::Medium;

            $findings[] = new Finding(
                id: 'dep-'.substr(hash('sha256', $key), 0, 16),
                ruleId: 'AUD-DEP-001',
                title: 'Known vulnerable dependency: '.$package,
                domain: AuditDomain::Security,
                severity: $severity,
                confidence: Confidence::Confirmed,
                summary: 'Composer reported '.($reference !== '' ? $reference.' for ' : 'a security advisory for ').$package.'.',
                whyItMatters: 'The selected Composer audit reported this dependency version as affected by a published security advisory. Reachability in the application has not been established.',
                evidence: EvidenceCollection::fromIterable([
                    new Evidence(
                        type: 'dependency',
                        reference: $package,
                        detail: implode('; ', array_filter([$reference, $affected !== '' ? 'Affected: '.$affected : ''])),
                    ),
                ]),
                affectedResources: [],
                recommendation: 'Read the advisory and update to a compatible non-affected version. Re-run Composer audit to verify the fix.',
                verificationNotes: $reportedSeverity === '' || Severity::tryFrom($reportedSeverity) === null
                    ? 'Composer did not provide a recognized severity; medium was used for triage.'
                    : 'Affected package identified by Composer audit; application exploitability was not assessed.',
                metadata: [
                    'advisory_id' => $id,
                    'cve' => $cve,
                    'advisory_title' => $title,
                    'affected_versions' => $affected,
                    'advisory_url' => $link,
                    'source' => 'composer_audit',
                ],
            );
        }

        return $findings;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function string(array $data, string $key): string
    {
        return is_string($data[$key] ?? null) ? $data[$key] : '';
    }
}
