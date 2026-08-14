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
