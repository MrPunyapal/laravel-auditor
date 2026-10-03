<?php

declare(strict_types=1);

use LaravelAuditor\Support\GitStatus;

it('parses staged, unstaged, and untracked paths', function () {
    $output = " M app/Models/User.php\0M  app/Http/Controllers/PostController.php\0?? tests/Feature/DirtyTest.php\0";

    expect(GitStatus::parsePorcelain($output))->toBe([
        'app/Http/Controllers/PostController.php',
        'app/Models/User.php',
        'tests/Feature/DirtyTest.php',
    ]);
});

it('keeps the destination of a rename and drops the original path', function () {
    // git -z emits the destination first, then the original in a second field.
    expect(GitStatus::parsePorcelain("R  app/New.php\0app/Old.php\0"))->toBe(['app/New.php']);
});

it('keeps deleted paths so a review still sees the change', function () {
    expect(GitStatus::parsePorcelain(" D app/Gone.php\0D  app/AlsoGone.php\0"))
        ->toBe(['app/AlsoGone.php', 'app/Gone.php']);
});

it('unquotes nothing and keeps unusual paths intact', function () {
    expect(GitStatus::parsePorcelain("?? app/Ünïcode File.php\0"))
        ->toBe(['app/Ünïcode File.php']);
});

it('rewrites repository-relative paths to application-relative paths', function () {
    $output = " M packages/api/app/Models/User.php\0 M packages/api/tests/Feature/DirtyTest.php\0";

    expect(GitStatus::parsePorcelain($output, 'packages/api/'))->toBe([
        'app/Models/User.php',
        'tests/Feature/DirtyTest.php',
    ]);
});

it('drops paths outside the application root instead of producing a parent reference', function () {
    $output = " M packages/api/../web/app/Other.php\0 M other/package/File.php\0";

    expect(GitStatus::parsePorcelain($output, 'packages/api/'))->toBe([]);
});

it('normalizes windows separators to forward slashes', function () {
    expect(GitStatus::parsePorcelain(" M app\\Models\\User.php\0"))->toBe(['app/Models/User.php']);
});

it('ignores configured prefixes on whole path segments only', function () {
    $output = " M storage/framework/cache.php\0?? vendor/pkg/File.php\0 M app/StoragePolicy.php\0";

    expect(GitStatus::parsePorcelain($output, '', ['storage', 'vendor']))
        ->toBe(['app/StoragePolicy.php']);
});

it('parses a diff name-status list and keeps both sides of a rename', function () {
    // git diff -z emits the original path first, then the destination. Status porcelain does the reverse.
    $output = "M\0app/Models/User.php\0R100\0app/Models/Old.php\0app/Models/Account.php\0D\0app/Gone.php\0";

    expect(GitStatus::parseDiffStatus($output))->toBe([
        'app/Gone.php',
        'app/Models/Account.php',
        'app/Models/Old.php',
        'app/Models/User.php',
    ]);
});

it('rewrites diff paths to the application root and drops siblings', function () {
    $output = "M\0packages/api/app/Models/User.php\0A\0other/File.php\0M\0packages/api/storage/framework/cache.php\0";

    expect(GitStatus::parseDiffStatus($output, 'packages/api/', ['storage']))
        ->toBe(['app/Models/User.php']);
});

it('keeps unusual diff paths intact', function () {
    expect(GitStatus::parseDiffStatus("A\0app/Ünïcode File.php\0"))
        ->toBe(['app/Ünïcode File.php']);
});

it('de-duplicates a file that is both staged and unstaged', function () {
    $output = "MM app/Models/User.php\0 M app/Models/User.php\0";

    expect(GitStatus::parsePorcelain($output))->toBe(['app/Models/User.php']);
});
