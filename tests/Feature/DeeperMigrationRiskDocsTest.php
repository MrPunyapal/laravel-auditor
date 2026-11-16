<?php

declare(strict_types=1);

it('documents opt-in source inspection and database-specific guidance', function () {
    $doc = (string) file_get_contents(dirname(__DIR__, 2).'/md/migration-risk.md');

    expect($doc)->toContain('--migration=', '--driver-guidance', 'without `require`', 'No indicators means');
});

it('keeps the deeper migration review skills in sync', function () {
    $base = dirname(__DIR__, 2);
    $standalone = (string) file_get_contents($base.'/resources/auditor/skills/laravel-audit-database/SKILL.md');
    $boost = (string) file_get_contents($base.'/resources/boost/skills/laravel-audit-database/SKILL.md');

    expect($standalone)->toBe($boost)
        ->and($standalone)->toContain('--driver-guidance', '--migration=', 'never certifies deployment safety');
});
