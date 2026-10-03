<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

/**
 * @param  list<array<string, mixed>>  $findings
 */
function baseFindingsFile(array $findings): string
{
    $path = sys_get_temp_dir().'/laravel-auditor-base-'.uniqid().'.json';

    file_put_contents($path, json_encode(['findings' => $findings], JSON_THROW_ON_ERROR));

    return $path;
}

/**
 * @return array<string, mixed>
 */
function baseFinding(string $id, ?string $reference = 'app/Models/User.php', string $severity = 'high'): array
{
    $finding = [
        'id' => $id,
        'rule_id' => 'AUD-SEC-001',
        'title' => 'Test finding',
        'domain' => 'security',
        'severity' => $severity,
        'confidence' => 'confirmed',
        'status' => 'open',
        'summary' => 'Summary',
        'why_it_matters' => 'Why',
        'evidence' => [],
    ];

    if ($reference !== null) {
        $finding['evidence'] = [
            ['type' => 'file', 'reference' => $reference, 'line' => 10],
        ];
    }

    return $finding;
}

/**
 * A committed branch whose only change since main is app/Models/User.php.
 * The working tree is clean, which is what a CI checkout looks like.
 */
function committedFeatureRepository(): string
{
    $repo = changedFilesRepository();

    writeRepositoryFile($repo, 'app/Models/User.php', '<?php');
    writeRepositoryFile($repo, 'app/Jobs/SendMail.php', '<?php');
    runGit($repo, 'add', '-A');
    runGit($repo, 'commit', '-qm', 'init');
    runGit($repo, 'branch', '-M', 'main');
    runGit($repo, 'checkout', '-q', '-b', 'feature');
    writeRepositoryFile($repo, 'app/Models/User.php', '<?php // edited');
    runGit($repo, 'add', '-A');
    runGit($repo, 'commit', '-qm', 'edit user');

    return $repo;
}

it('fails a clean CI checkout only for findings on the branch diff', function () {
    $repo = committedFeatureRepository();

    try {
        useDirtyRepository($repo);

        $untouched = baseFindingsFile([baseFinding('F-old', 'app/Jobs/SendMail.php')]);

        $this->artisan('auditor:ci', ['--findings' => $untouched, '--fail-on' => 'high', '--base' => 'main'])
            ->expectsOutputToContain('Base scope (main): 1 changed file(s)')
            ->expectsOutputToContain('CI passed')
            ->assertSuccessful();

        unlink($untouched);

        $touched = baseFindingsFile([baseFinding('F-new', 'app/Models/User.php')]);

        $this->artisan('auditor:ci', ['--findings' => $touched, '--fail-on' => 'high', '--base' => 'main'])
            ->expectsOutputToContain('CI failed')
            ->assertFailed();

        // The same committed file is invisible to --dirty: a clean checkout has no uncommitted files.
        $this->artisan('auditor:ci', ['--findings' => $touched, '--fail-on' => 'high', '--dirty' => true])
            ->expectsOutputToContain('Dirty scope: 0 uncommitted file(s)')
            ->expectsOutputToContain('CI passed')
            ->assertSuccessful();

        unlink($touched);
    } finally {
        removeRepository($repo);
    }
});

it('records the base ref on a scoped report', function () {
    $repo = committedFeatureRepository();

    try {
        useDirtyRepository($repo);

        $findings = baseFindingsFile([
            baseFinding('F-touched', 'app/Models/User.php'),
            baseFinding('F-untouched', 'app/Jobs/SendMail.php'),
        ]);
        $report = sys_get_temp_dir().DIRECTORY_SEPARATOR.'laravel-auditor-base-report-'.uniqid().'.json';

        $exit = Artisan::call('auditor:report', [
            '--format' => 'json',
            '--findings' => $findings,
            '--base' => 'main',
            '--output' => $report,
        ]);

        expect($exit)->toBe(0);

        $payload = json_decode((string) file_get_contents($report), true);

        expect($payload['summary']['total_findings'])->toBe(1);
        expect($payload['findings'][0]['id'])->toBe('F-touched');
        expect($payload['meta']['scope'])->toBe('base');
        expect($payload['meta']['scope_base'])->toBe('main');
        expect($payload['meta']['scope_file_count'])->toBe(1);
        expect($payload['meta']['scope_truncated'])->toBeFalse();
        expect($payload['meta']['scope_total_findings'])->toBe(2);

        unlink($findings);
        unlink($report);
    } finally {
        removeRepository($repo);
    }
});

it('does not treat uncommitted files as part of the base scope unless --dirty is also set', function () {
    $repo = committedFeatureRepository();

    try {
        writeRepositoryFile($repo, 'app/Jobs/SendMail.php', '<?php // local edit');
        useDirtyRepository($repo);

        $findings = baseFindingsFile([baseFinding('F-local', 'app/Jobs/SendMail.php')]);

        $this->artisan('auditor:ci', ['--findings' => $findings, '--fail-on' => 'high', '--base' => 'main'])
            ->expectsOutputToContain('CI passed')
            ->assertSuccessful();

        $this->artisan('auditor:ci', ['--findings' => $findings, '--fail-on' => 'high', '--base' => 'main', '--dirty' => true])
            ->expectsOutputToContain('plus uncommitted files')
            ->expectsOutputToContain('CI failed')
            ->assertFailed();

        unlink($findings);
    } finally {
        removeRepository($repo);
    }
});

it('keeps a finding that names no file when the branch diff is empty', function () {
    $repo = changedFilesRepository();

    try {
        writeRepositoryFile($repo, 'app/Models/User.php', '<?php');
        runGit($repo, 'add', '-A');
        runGit($repo, 'commit', '-qm', 'init');
        runGit($repo, 'branch', '-M', 'main');

        useDirtyRepository($repo);

        $unscoped = baseFindingsFile([baseFinding('F-unscoped', null)]);

        $this->artisan('auditor:ci', ['--findings' => $unscoped, '--fail-on' => 'high', '--base' => 'main'])
            ->expectsOutputToContain('Base scope (main): 0 changed file(s)')
            ->expectsOutputToContain('CI failed')
            ->assertFailed();

        unlink($unscoped);
    } finally {
        removeRepository($repo);
    }
});

it('ignores configured path prefixes in the branch diff', function () {
    $repo = changedFilesRepository();

    try {
        writeRepositoryFile($repo, 'app/Models/User.php', '<?php');
        runGit($repo, 'add', '-A');
        runGit($repo, 'commit', '-qm', 'init');
        runGit($repo, 'branch', '-M', 'main');
        runGit($repo, 'checkout', '-q', '-b', 'feature');
        writeRepositoryFile($repo, 'storage/framework/cache.php', '<?php');
        runGit($repo, 'add', '-A');
        runGit($repo, 'commit', '-qm', 'cache');

        useDirtyRepository($repo);

        $findings = baseFindingsFile([baseFinding('F-cache', 'storage/framework/cache.php')]);

        $this->artisan('auditor:ci', ['--findings' => $findings, '--fail-on' => 'low', '--base' => 'main'])
            ->expectsOutputToContain('Base scope (main): 0 changed file(s)')
            ->assertSuccessful();

        unlink($findings);
    } finally {
        removeRepository($repo);
    }
});

it('fails when the base ref does not exist', function () {
    $repo = committedFeatureRepository();

    try {
        useDirtyRepository($repo);

        $findings = baseFindingsFile([baseFinding('F-1')]);

        $exit = Artisan::call('auditor:ci', ['--findings' => $findings, '--base' => 'no-such-ref']);

        expect($exit)->not->toBe(0);
        expect(Artisan::output())
            ->toContain('Cannot resolve --base scope')
            ->toContain('no-such-ref')
            ->toContain('Fetch the base ref');

        $this->artisan('auditor:report', ['--findings' => $findings, '--base' => 'no-such-ref'])
            ->expectsOutputToContain('Cannot resolve --base scope')
            ->assertFailed();

        unlink($findings);
    } finally {
        removeRepository($repo);
    }
});

it('fails when --base is empty', function () {
    $findings = baseFindingsFile([baseFinding('F-1')]);

    try {
        $this->artisan('auditor:ci', ['--findings' => $findings, '--base' => ''])
            ->expectsOutputToContain('Pass a commit, branch, or tag to --base')
            ->assertFailed();
    } finally {
        unlink($findings);
    }
});

it('fails instead of gating on a truncated change set', function () {
    $repo = changedFilesRepository();

    try {
        writeRepositoryFile($repo, 'app/Keep.php', '<?php');
        runGit($repo, 'add', '-A');
        runGit($repo, 'commit', '-qm', 'init');
        runGit($repo, 'branch', '-M', 'main');
        runGit($repo, 'checkout', '-q', '-b', 'feature');
        writeRepositoryFile($repo, 'app/One.php', '<?php');
        writeRepositoryFile($repo, 'app/Two.php', '<?php');
        runGit($repo, 'add', '-A');
        runGit($repo, 'commit', '-qm', 'two files');

        useDirtyRepository($repo);
        config(['laravel-auditor.changed_files.max_files' => 1]);

        $findings = baseFindingsFile([baseFinding('F-1', 'app/Two.php')]);

        $this->artisan('auditor:ci', ['--findings' => $findings, '--fail-on' => 'high', '--base' => 'main'])
            ->expectsOutputToContain('changed_files.max_files')
            ->assertFailed();

        unlink($findings);
    } finally {
        removeRepository($repo);
    }
});
