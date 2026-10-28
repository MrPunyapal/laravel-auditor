<?php

declare(strict_types=1);

it('ships a non-mutating scheduled Composer audit workflow example', function () {
    $path = __DIR__.'/../../resources/auditor/examples/composer-security-audit.yml';

    expect(file_exists($path))->toBeTrue();

    $contents = (string) file_get_contents($path);

    expect($contents)
        ->toContain('schedule:')
        ->toContain('workflow_dispatch:')
        ->toContain('contents: read')
        ->toContain('composer audit --locked --no-interaction --no-plugins')
        ->not->toContain('composer install')
        ->not->toContain('composer update')
        ->not->toContain('composer audit fix');
});
