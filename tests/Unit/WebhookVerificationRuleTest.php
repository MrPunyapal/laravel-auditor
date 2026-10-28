<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use LaravelAuditor\Audit\Enums\AuditDomain;
use LaravelAuditor\Audit\Rules\RuleRegistry;

it('requires a missing webhook authenticity boundary rather than just a CSRF exemption', function () {
    $registry = new RuleRegistry(new Filesystem, [__DIR__.'/../../resources/auditor/rules']);
    $rule = $registry->find('AUD-SEC-016');

    expect($rule)->not->toBeNull()
        ->and($rule->domain)->toBe(AuditDomain::Security)
        ->and($rule->evidence)->toHaveCount(3)
        ->and(implode(' ', $rule->falsePositiveConsiderations))->toContain('alone is not a vulnerability')
        ->and(implode(' ', $rule->evidence))->toContain('middleware')
        ->and($rule->references)->toHaveCount(2);
});
