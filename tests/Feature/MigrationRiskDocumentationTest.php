<?php

declare(strict_types=1);

it('keeps migration risk guidance identical in standalone and Boost skills', function () {
    $standalone = file_get_contents(__DIR__.'/../../resources/auditor/skills/laravel-audit-database/SKILL.md');
    $boost = file_get_contents(__DIR__.'/../../resources/boost/skills/laravel-audit-database/SKILL.md');

    expect($standalone)->toBe($boost)
        ->and($standalone)->toContain('auditor:migration-risk', 'does **not** prove');
});

it('documents the read-only schema comparison limitations', function () {
    $documentation = file_get_contents(__DIR__.'/../../md/migration-risk.md');

    expect($documentation)->toContain('deployment_safety_verified', 'snapshot_structure_only', 'disposable database')
        ->and($documentation)->toContain('table rename appears as a removal indicator')
        ->and($documentation)->not->toBeEmpty();
});
