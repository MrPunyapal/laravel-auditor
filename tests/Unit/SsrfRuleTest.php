<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use LaravelAuditor\Audit\Enums\AuditDomain;
use LaravelAuditor\Audit\Rules\RuleRegistry;

it('requires evidence of untrusted input reaching a server-side request', function () {
    $registry = new RuleRegistry(new Filesystem, [__DIR__.'/../../resources/auditor/rules']);
    $rule = $registry->find('AUD-SEC-014');

    expect($rule)->not->toBeNull()
        ->and($rule->domain)->toBe(AuditDomain::Security)
        ->and($rule->evidence)->toHaveCount(3)
        ->and($rule->falsePositiveConsiderations)->not->toBeEmpty()
        ->and(implode(' ', $rule->falsePositiveConsiderations))->toContain('allow-list')
        ->and(implode(' ', $rule->evidence))->toContain('route or job');
});
