<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use LaravelAuditor\Audit\Enums\AuditDomain;
use LaravelAuditor\Audit\Rules\RuleRegistry;

it('ships the architecture code-smell catalog', function () {
    $registry = new RuleRegistry(
        new Filesystem,
        [__DIR__.'/../../resources/auditor/rules'],
    );

    $ids = [
        'AUD-ARC-006',
        'AUD-ARC-007',
        'AUD-ARC-008',
        'AUD-ARC-009',
        'AUD-ARC-010',
        'AUD-ARC-011',
    ];

    foreach ($ids as $id) {
        $rule = $registry->find($id);

        expect($rule)->not->toBeNull($id);
        expect($rule->domain)->toBe(AuditDomain::Architecture);
        expect($rule->evidence)->not->toBeEmpty($id);
        expect($rule->falsePositiveConsiderations)->not->toBeEmpty($id);
    }

    expect($registry->count())->toBe(81);
    expect($registry->forDomain(AuditDomain::Architecture))->toHaveCount(14);
});
