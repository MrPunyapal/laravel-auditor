<?php

declare(strict_types=1);

use LaravelAuditor\Audit\Domains\DomainRegistry;
use LaravelAuditor\Audit\Enums\AuditDomain;
use LaravelAuditor\Audit\Enums\Confidence;
use LaravelAuditor\Audit\Enums\FindingStatus;
use LaravelAuditor\Audit\Enums\Severity;

it('exposes the six core audit domains', function () {
    $domains = AuditDomain::core();

    expect($domains)->toHaveCount(6);
    expect(array_map(fn (AuditDomain $d): string => $d->value, $domains))->toBe([
        'security',
        'performance',
        'architecture',
        'database',
        'testing',
        'conventions',
    ]);
});

it('assigns human-readable labels to domains', function () {
    expect(AuditDomain::Security->label())->toBe('Security');
    expect(AuditDomain::Conventions->label())->toBe('Laravel conventions');
});

it('orders severities by weight', function () {
    expect(Severity::Critical->weight())->toBe(5);
    expect(Severity::High->weight())->toBe(4);
    expect(Severity::Medium->weight())->toBe(3);
    expect(Severity::Low->weight())->toBe(2);
    expect(Severity::Info->weight())->toBe(1);
});

it('orders confidence by weight', function () {
    expect(Confidence::Confirmed->weight())->toBe(4);
    expect(Confidence::High->weight())->toBe(3);
    expect(Confidence::Medium->weight())->toBe(2);
    expect(Confidence::Low->weight())->toBe(1);
});

it('describes each domain', function () {
    expect(AuditDomain::Security->description())->toContain('Authorization');
    expect(AuditDomain::Conventions->description())->toContain('Laravel');
});

it('exposes the domain registry', function () {
    $registry = new DomainRegistry;

    expect($registry->keys())->toBe([
        'security',
        'performance',
        'architecture',
        'database',
        'testing',
        'conventions',
    ]);
    expect($registry->all()['security']['label'])->toBe('Security');
    expect($registry->core())->toHaveCount(6);
});

it('accepts an explicit domain map', function () {
    $registry = new DomainRegistry([
        'custom' => ['label' => 'Custom', 'description' => 'A future domain.'],
    ]);

    expect($registry->keys())->toBe(['custom']);
});

it('provides status labels', function () {
    expect(FindingStatus::Open->label())->toBe('Open');
    expect(FindingStatus::Accepted->label())->toBe('Accepted');
    expect(FindingStatus::Dismissed->label())->toBe('Dismissed');
    expect(FindingStatus::Fixed->label())->toBe('Fixed');
});
