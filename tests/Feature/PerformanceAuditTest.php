<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

it('scopes the performance rule catalog through the rules command', function () {
    $exit = Artisan::call('auditor:rules', ['--domain' => 'performance', '--json' => true]);

    expect($exit)->toBe(0);

    $payload = json_decode(Artisan::output(), true);
    $ids = array_column($payload, 'id');

    // 18 core rules + AUD-LW-003/AUD-FIL-003/AUD-IN-003 + AUD-QUE-003 + AUD-DSA-004.
    expect(count($ids))->toBe(23);

    foreach (['AUD-PER-008', 'AUD-PER-011', 'AUD-PER-012', 'AUD-PER-018'] as $expected) {
        expect($ids)->toContain($expected);
    }

    expect(array_unique(array_column($payload, 'domain')))->toBe(['performance']);
});

it('renders the packaged performance example finding', function () {
    $exit = Artisan::call('auditor:report', ['--example' => true, '--format' => 'json']);

    expect($exit)->toBe(0);

    $payload = json_decode(Artisan::output(), true);

    $performance = array_values(array_filter(
        $payload['findings'],
        static fn (array $finding): bool => $finding['rule_id'] === 'AUD-PER-008',
    ));

    expect($performance)->toHaveCount(1);
    expect($performance[0]['metadata']['impact'])->toHaveKeys(['resource', 'mechanism', 'amplification']);
    expect($performance[0]['verification_notes'])->toBeString();
});

it('keeps auditor and Boost performance audit resources identical', function () {
    $pairs = [
        [
            __DIR__.'/../../resources/auditor/skills/laravel-audit-performance/SKILL.md',
            __DIR__.'/../../resources/boost/skills/laravel-audit-performance/SKILL.md',
        ],
        [
            __DIR__.'/../../resources/auditor/guidelines/performance.md',
            __DIR__.'/../../resources/boost/guidelines/performance.blade.php',
        ],
    ];

    // The skill copies must be byte-identical (Boost consumes them directly).
    [$auditorSkill, $boostSkill] = $pairs[0];
    expect(file_get_contents($auditorSkill))->toBe(file_get_contents($boostSkill));

    // The guideline mirror condenses rather than duplicates; both must teach
    // the verification-first contract.
    foreach ($pairs[1] as $guideline) {
        $contents = mb_strtolower((string) file_get_contents($guideline));

        expect(file_exists($guideline))->toBeTrue();
        expect($contents)->toContain('equivalen');
        expect($contents)->toContain('impact');
    }
});
