<?php

declare(strict_types=1);

use LaravelAuditor\Audit\Enums\AuditDomain;
use LaravelAuditor\Audit\Findings\ComposerAbandonedFindings;
use LaravelAuditor\Audit\Findings\Finding;

it('creates stable low-severity findings for abandoned Composer packages', function () {
    $audit = [
        'abandoned' => [
            'vendor/old' => 'vendor/new',
            'vendor/without-replacement' => null,
        ],
    ];

    $findings = ComposerAbandonedFindings::fromAudit($audit);

    expect($findings)->toHaveCount(2)
        ->and($findings[0]->ruleId)->toBe('AUD-DEP-002')
        ->and($findings[0]->domain)->toBe(AuditDomain::Conventions)
        ->and($findings[0]->severity->value)->toBe('low')
        ->and($findings[0]->metadata['replacement_package'])->toBe('vendor/new')
        ->and($findings[0]->evidence->toArray()[0]['type'])->toBe('dependency')
        ->and($findings[0]->verificationNotes)->toContain('not been verified')
        ->and($findings[1]->metadata['replacement_package'])->toBeNull()
        ->and(ComposerAbandonedFindings::fromAudit($audit)[0]->id)->toBe($findings[0]->id);

    expect(Finding::fromArray($findings[0]->toArray())->id)->toBe($findings[0]->id);
});

it('skips incomplete abandoned-package keys', function () {
    expect(ComposerAbandonedFindings::fromAudit(['abandoned' => []]))->toBe([]);
    expect(ComposerAbandonedFindings::fromAudit(['abandoned' => ['' => null]]))->toBe([]);
});
