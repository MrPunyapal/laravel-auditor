<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use LaravelAuditor\Audit\Enums\AuditDomain;
use LaravelAuditor\Audit\Rules\RuleDefinition;
use LaravelAuditor\Audit\Rules\RuleRegistry;

/**
 * Builds the rule registry over the package's built-in catalog.
 */
function performanceRuleRegistry(): RuleRegistry
{
    return new RuleRegistry(
        new Filesystem,
        [dirname(__DIR__, 2).'/resources/auditor/rules'],
    );
}

it('ships the deep performance rule catalog', function () {
    $registry = performanceRuleRegistry();

    $ids = [
        'AUD-PER-008',
        'AUD-PER-009',
        'AUD-PER-010',
        'AUD-PER-011',
        'AUD-PER-012',
        'AUD-PER-013',
        'AUD-PER-014',
        'AUD-PER-015',
        'AUD-PER-016',
        'AUD-PER-017',
        'AUD-PER-018',
    ];

    foreach ($ids as $id) {
        $rule = $registry->find($id);

        expect($rule)->not->toBeNull("{$id} should exist");
        expect($rule?->domain)->toBe(AuditDomain::Performance);
        expect($rule?->description)->not->toBe('');
        expect($rule?->whyItMatters)->not->toBe('');
        expect($rule?->recommendation)->not->toBe('');
        expect($rule?->references)->not->toBeEmpty();
    }
});

it('keeps every performance rule evidence-first with false-positive guidance', function () {
    // 18 AUD-PER-* rules plus cross-file members of the domain:
    // AUD-LW-003, AUD-FIL-003, AUD-IN-003, AUD-QUE-003, and AUD-DSA-004.
    $rules = performanceRuleRegistry()->forDomain(AuditDomain::Performance);

    expect($rules)->toHaveCount(23);

    foreach ($rules as $rule) {
        expect($rule->domain)->toBe(AuditDomain::Performance);
        expect($rule->evidence, $rule->id)->not->toBeEmpty();
        expect($rule->falsePositiveConsiderations, $rule->id)->not->toBeEmpty();

        // Rules that describe an optimization must state when it does NOT apply.
        if (in_array($rule->id, ['AUD-PER-008', 'AUD-PER-009', 'AUD-PER-010'], true)) {
            expect(count($rule->falsePositiveConsiderations), $rule->id)->toBeGreaterThanOrEqual(3);
        }
    }
});

it('teaches collection reuse before recommending query-side aggregates', function () {
    $rule = performanceRuleRegistry()->find('AUD-PER-008');

    expect($rule)->not->toBeNull();

    $considerations = implode(' ', array_map('strval', $rule?->falsePositiveConsiderations ?? []));

    // The most important false positive: the collection is needed elsewhere.
    expect(mb_strtolower($considerations))->toContain('reus');
});

it('does not flag already-loaded relationships as count candidates', function () {
    $rule = performanceRuleRegistry()->find('AUD-PER-010');

    expect($rule)->not->toBeNull();

    $considerations = mb_strtolower(implode(' ', array_map('strval', $rule?->falsePositiveConsiderations ?? [])));

    // Already-loaded relationship collections make ->count() free; the rule must say so.
    expect($considerations)->toContain('already');
});

it('requires semantic verification guidance on PHP-vs-SQL rules', function () {
    $registry = performanceRuleRegistry();

    foreach (['AUD-PER-009', 'AUD-PER-013', 'AUD-PER-018'] as $id) {
        $rule = $registry->find($id);
        $text = mb_strtolower($rule?->recommendation.' '.implode(' ', array_map('strval', $rule?->falsePositiveConsiderations ?? [])));

        $mentionsVerification = str_contains($text, 'semantically')
            || str_contains($text, 'equivalen')
            || str_contains($text, 'verify');

        expect($mentionsVerification, "{$id} must demand equivalence/verification")->toBeTrue();
    }
});

it('gates ecosystem performance rules behind their packages', function () {
    $registry = performanceRuleRegistry();

    $gated = [
        'AUD-LW-003' => ['livewire/livewire'],
        'AUD-FIL-003' => ['filament/filament'],
        'AUD-IN-003' => ['inertiajs/inertia-laravel'],
    ];

    foreach ($gated as $id => [$package]) {
        $rule = $registry->find($id);

        expect($rule)->not->toBeNull("{$id} should exist");
        expect($rule?->domain)->toBe(AuditDomain::Performance);
        expect($rule?->applies('12.0.0', []), "{$id} must not apply without its package")->toBeFalse();
        expect($rule?->applies('12.0.0', [$package => '1.0.0']), "{$id} must apply with its package")->toBeTrue();
    }
});

it('applies core performance rules without any ecosystem packages', function () {
    $applicable = performanceRuleRegistry()->applicable('12.0.0', []);
    $ids = array_map(static fn (RuleDefinition $rule): string => $rule->id, $applicable);

    foreach (['AUD-PER-008', 'AUD-PER-011', 'AUD-PER-012', 'AUD-PER-016', 'AUD-PER-018'] as $expected) {
        expect($ids)->toContain($expected);
    }

    foreach (['AUD-LW-003', 'AUD-FIL-003', 'AUD-IN-003'] as $unexpected) {
        expect($ids)->not->toContain($unexpected);
    }
});
