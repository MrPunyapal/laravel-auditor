<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LaravelAuditor\Context\Collectors\ChangedFilesCollector;
use LaravelAuditor\Context\ContextRegistry;
use LaravelAuditor\Support\GitStatus;

it('registers the changed files collector', function () {
    $registry = app(ContextRegistry::class);

    expect($registry->has('changed_files'))->toBeTrue();
    expect($registry->get('changed_files'))->toBeInstanceOf(ChangedFilesCollector::class);
});

it('lists uncommitted files from a real repository', function () {
    $path = changedFilesRepository();

    try {
        writeRepositoryFile($path, 'app/Models/User.php', '<?php');
        writeRepositoryFile($path, 'app/Models/Post.php', '<?php');
        runGit($path, 'add', '-A');
        runGit($path, 'commit', '-qm', 'init');

        writeRepositoryFile($path, 'app/Models/User.php', '<?php // edited');
        writeRepositoryFile($path, 'app/Http/Controllers/PostController.php', '<?php');
        writeRepositoryFile($path, 'storage/framework/cache.php', '<?php');
        writeRepositoryFile($path, 'vendor/pkg/Installed.php', '<?php');
        runGit($path, 'add', 'storage/framework/cache.php', 'vendor/pkg/Installed.php');

        $result = (new GitStatus($path))->changedFiles(ignore: ['storage', 'vendor']);

        expect($result['available'])->toBeTrue();
        expect($result['files'])->toBe([
            'app/Http/Controllers/PostController.php',
            'app/Models/User.php',
        ]);
    } finally {
        removeRepository($path);
    }
});

it('reports unavailable with a reason outside a git repository', function () {
    $path = sys_get_temp_dir().'/laravel-auditor-nogit-'.uniqid();

    mkdir($path, 0777, true);

    try {
        $result = (new GitStatus($path))->changedFiles();

        expect($result['available'])->toBeFalse();
        expect($result['reason'])->toBeString()->not->toBeEmpty();
        expect($result['files'])->toBe([]);
    } finally {
        removeRepository($path);
    }
});

it('truncates the file list at the configured maximum', function () {
    $path = changedFilesRepository();

    try {
        for ($i = 0; $i < 5; $i++) {
            writeRepositoryFile($path, "app/File{$i}.php", '<?php');
        }

        $result = (new GitStatus($path))->changedFiles(max: 2);

        expect($result['available'])->toBeTrue();
        expect($result['files'])->toHaveCount(2);
        expect($result['truncated'])->toBeTrue();
    } finally {
        removeRepository($path);
    }
});

it('excludes untracked files when the option is disabled', function () {
    $path = changedFilesRepository();

    try {
        writeRepositoryFile($path, 'app/Models/User.php', '<?php');
        runGit($path, 'add', '-A');
        runGit($path, 'commit', '-qm', 'init');

        writeRepositoryFile($path, 'app/Models/User.php', '<?php // edited');
        writeRepositoryFile($path, 'app/Models/BrandNew.php', '<?php');

        expect((new GitStatus($path))->changedFiles()['files'])
            ->toBe(['app/Models/BrandNew.php', 'app/Models/User.php']);

        expect((new GitStatus($path))->changedFiles(includeUntracked: false)['files'])
            ->toBe(['app/Models/User.php']);
    } finally {
        removeRepository($path);
    }
});

it('dumps the changed files collector through artisan', function () {
    $exit = Artisan::call('auditor:context', ['collector' => 'changed_files']);

    expect($exit)->toBe(0);

    $payload = json_decode(Artisan::output(), true);

    expect($payload)->toHaveKeys(['available', 'count', 'files']);
});
