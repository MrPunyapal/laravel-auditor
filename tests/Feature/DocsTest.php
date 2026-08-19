<?php

declare(strict_types=1);

it('keeps Docsmith markdown sources with frontmatter', function () {
    $pages = glob(dirname(__DIR__, 2).'/md/*.md') ?: [];

    expect($pages)->not->toBeEmpty();

    foreach ($pages as $page) {
        $contents = (string) file_get_contents($page);

        expect($contents)
            ->toStartWith('---')
            ->toContain('title:')
            ->toContain('order:')
            ->toContain('slug:');
    }
});

it('has a Docsmith build entry point', function () {
    expect(file_exists(dirname(__DIR__, 2).'/build-docs.php'))->toBeTrue();
    expect((string) file_get_contents(dirname(__DIR__, 2).'/build-docs.php'))->toContain('Docsmith::make()');
});

it('configures a single Open Graph cover image', function () {
    $build = (string) file_get_contents(dirname(__DIR__, 2).'/build-docs.php');

    expect($build)
        ->toContain('ogTemplate')
        ->toContain('favicon(')
        ->toContain('accentColor(')
        ->toContain('captureOg(')
        ->toContain("editPrefix('md')")
        ->toContain("scope: 'all'")
        ->not->toContain("scope: 'per-page'");
});

it('keeps an Open Graph card template with docsmith tokens', function () {
    $template = (string) file_get_contents(dirname(__DIR__, 2).'/resources/docs/og-card.html');

    expect($template)
        ->toContain('{title}')
        ->toContain('{description}')
        ->toContain('{accent_color}');
});

it('keeps a favicon for the docs site', function () {
    expect(file_exists(dirname(__DIR__, 2).'/resources/docs/favicon.svg'))->toBeTrue();
});
