<?php

declare(strict_types=1);

it('ships a manual least-privilege change-review workflow with separate evidence and policy steps', function () {
    $path = __DIR__.'/../../resources/auditor/examples/change-review.yml';

    expect(file_exists($path))->toBeTrue();

    $contents = (string) file_get_contents($path);

    expect($contents)
        ->toContain('workflow_dispatch:')
        ->toContain('contents: read', 'actions: read')
        ->toContain('persist-credentials: false', 'fetch-depth: 0')
        ->toContain('.head_sha == $sha', '.head_repository.full_name == $repo')
        ->toContain('laravel-auditor-before', 'laravel-auditor-after')
        ->toContain('php artisan auditor:review', 'php artisan auditor:ci')
        ->toContain('--fail-on-regression', '--format=github')
        ->toContain('publish_annotations:', 'default: false')
        ->toContain('Evidence review unavailable or failed')
        ->toContain('Finding policy not evaluated')
        ->not->toContain('pull_request_target:', 'pull_request:', 'contents: write', 'security-events: write')
        ->not->toContain('php artisan migrate', 'composer update', 'actions/upload-artifact');
});
