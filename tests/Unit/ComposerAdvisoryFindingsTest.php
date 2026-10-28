<?php

declare(strict_types=1);

use LaravelAuditor\Audit\Findings\ComposerAdvisoryFindings;
use LaravelAuditor\Audit\Findings\Finding;

it('converts Composer advisories into stable evidence-backed findings', function () {
    $audit = ['advisories' => [[
        'package' => 'laravel/framework',
        'advisory_id' => 'GHSA-example',
        'title' => 'Example',
        'cve' => 'CVE-2026-0001',
        'affected_versions' => '<13.29.0',
        'link' => 'https://example.test/advisory',
        'severity' => 'critical',
    ]]];

    $finding = ComposerAdvisoryFindings::fromAudit($audit)[0];

    expect($finding)->toBeInstanceOf(Finding::class)
        ->and($finding->ruleId)->toBe('AUD-DEP-001')
        ->and($finding->severity->value)->toBe('critical')
        ->and($finding->confidence->value)->toBe('confirmed')
        ->and($finding->evidence->toArray()[0]['type'])->toBe('dependency')
        ->and($finding->metadata['cve'])->toBe('CVE-2026-0001')
        ->and(ComposerAdvisoryFindings::fromAudit($audit)[0]->id)->toBe($finding->id);

    expect(Finding::fromArray($finding->toArray())->id)->toBe($finding->id);
});

it('does not invent severity when Composer does not provide one', function () {
    $findings = ComposerAdvisoryFindings::fromAudit(['advisories' => [[
        'package' => 'acme/package',
        'advisory_id' => 'GHSA-unknown',
        'severity' => '',
    ]]]);

    expect($findings)->toHaveCount(1)
        ->and($findings[0]->severity->value)->toBe('medium')
        ->and($findings[0]->verificationNotes)->toContain('medium was used for triage');
});

it('does not generate findings for empty or invalid package entries', function () {
    expect(ComposerAdvisoryFindings::fromAudit(['advisories' => []]))->toBe([]);
    expect(ComposerAdvisoryFindings::fromAudit(['advisories' => [['package' => '']]]))->toBe([]);
});
