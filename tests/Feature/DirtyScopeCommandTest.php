<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

function dirtyFindingsFile(array $findings): string
{
    $path = sys_get_temp_dir().'/laravel-auditor-dirty-'.uniqid().'.json';

    file_put_contents($path, json_encode(['findings' => $findings], JSON_THROW_ON_ERROR));

    return $path;
}

function dirtyFinding(string $id, string $reference, string $severity = 'high'): array
{
    return [
        'id' => $id,
        'rule_id' => 'AUD-SEC-001',
        'title' => 'Test finding',
        'domain' => 'security',
        'severity' => $severity,
        'confidence' => 'confirmed',
        'status' => 'open',
        'summary' => 'Summary',
        'why_it_matters' => 'Why',
        'evidence' => [
            ['type' => 'file', 'reference' => $reference, 'line' => 10],
        ],
    ];
}

it('limits a report to findings touching uncommitted files', function () {
    $repo = changedFilesRepository();

    try {
        writeRepositoryFile($repo, 'app/Models/User.php', '<?php');
        runGit($repo, 'add', '-A');
        runGit($repo, 'commit', '-qm', 'init');
        writeRepositoryFile($repo, 'app/Models/User.php', '<?php // edited');

        useDirtyRepository($repo);

        $findings = dirtyFindingsFile([
            dirtyFinding('F-touched', 'app/Models/User.php'),
            dirtyFinding('F-untouched', 'app/Jobs/SendMail.php'),
        ]);

        $report = sys_get_temp_dir().DIRECTORY_SEPARATOR.'laravel-auditor-dirty-report-'.uniqid().'.json';

        $exit = Artisan::call('auditor:report', [
            '--format' => 'json',
            '--findings' => $findings,
            '--dirty' => true,
            '--output' => $report,
        ]);

        expect($exit)->toBe(0);

        $payload = json_decode((string) file_get_contents($report), true);

        expect($payload['summary']['total_findings'])->toBe(1);
        expect($payload['findings'][0]['id'])->toBe('F-touched');
        expect($payload['meta']['scope'])->toBe('dirty');
        expect($payload['meta']['scope_file_count'])->toBe(1);
        expect($payload['meta']['scope_total_findings'])->toBe(2);

        unlink($findings);
        unlink($report);
    } finally {
        removeRepository($repo);
    }
});

it('fails CI only on dirty findings', function () {
    $repo = changedFilesRepository();

    try {
        writeRepositoryFile($repo, 'app/Jobs/SendMail.php', '<?php');
        runGit($repo, 'add', '-A');
        runGit($repo, 'commit', '-qm', 'init');
        writeRepositoryFile($repo, 'app/Models/User.php', '<?php // edited');

        useDirtyRepository($repo);

        // The high finding sits in an untouched file, so CI must stay green.
        $untouched = dirtyFindingsFile([dirtyFinding('F-old', 'app/Jobs/SendMail.php')]);

        $this->artisan('auditor:ci', ['--findings' => $untouched, '--fail-on' => 'high', '--dirty' => true])
            ->expectsOutputToContain('CI passed')
            ->assertSuccessful();

        unlink($untouched);

        // Move the high finding onto a changed file and CI must fail.
        $touched = dirtyFindingsFile([dirtyFinding('F-new', 'app/Models/User.php')]);

        $this->artisan('auditor:ci', ['--findings' => $touched, '--fail-on' => 'high', '--dirty' => true])
            ->expectsOutputToContain('CI failed')
            ->assertFailed();

        unlink($touched);
    } finally {
        removeRepository($repo);
    }
});

it('passes CI on a clean working tree', function () {
    $repo = changedFilesRepository();

    try {
        writeRepositoryFile($repo, 'app/Models/User.php', '<?php');
        runGit($repo, 'add', '-A');
        runGit($repo, 'commit', '-qm', 'init');

        useDirtyRepository($repo);

        $findings = dirtyFindingsFile([dirtyFinding('F-1', 'app/Models/User.php')]);

        $this->artisan('auditor:ci', ['--findings' => $findings, '--fail-on' => 'critical', '--dirty' => true])
            ->expectsOutputToContain('Dirty scope: 0 uncommitted file(s)')
            ->assertSuccessful();

        unlink($findings);
    } finally {
        removeRepository($repo);
    }
});

it('excludes findings that only reference routes or config keys', function () {
    $repo = changedFilesRepository();

    try {
        writeRepositoryFile($repo, 'app/Models/User.php', '<?php');

        useDirtyRepository($repo);

        $findings = dirtyFindingsFile([dirtyFinding('F-route', 'GET api/users.index')]);

        $this->artisan('auditor:ci', ['--findings' => $findings, '--fail-on' => 'low', '--dirty' => true])
            ->expectsOutputToContain('Dirty scope')
            ->assertSuccessful();

        unlink($findings);
    } finally {
        removeRepository($repo);
    }
});

it('honors the changed_files ignore list when scoping', function () {
    $repo = changedFilesRepository();

    try {
        writeRepositoryFile($repo, 'app/Models/User.php', '<?php');
        writeRepositoryFile($repo, 'storage/framework/cache.php', '<?php');

        useDirtyRepository($repo);

        $findings = dirtyFindingsFile([dirtyFinding('F-cache', 'storage/framework/cache.php')]);

        $this->artisan('auditor:ci', ['--findings' => $findings, '--fail-on' => 'low', '--dirty' => true])
            ->expectsOutputToContain('Dirty scope: 1 uncommitted file(s)')
            ->assertSuccessful();

        unlink($findings);
    } finally {
        removeRepository($repo);
    }
});

it('fails with a clear reason when the dirty scope cannot be resolved', function () {
    $notARepo = sys_get_temp_dir().'/laravel-auditor-dirty-nogit-'.uniqid();

    mkdir($notARepo, 0777, true);

    try {
        useDirtyRepository($notARepo);

        $findings = dirtyFindingsFile([dirtyFinding('F-1', 'app/Models/User.php')]);

        $this->artisan('auditor:report', ['--findings' => $findings, '--dirty' => true])
            ->expectsOutputToContain('Cannot resolve --dirty scope')
            ->assertFailed();

        $this->artisan('auditor:ci', ['--findings' => $findings, '--dirty' => true])
            ->expectsOutputToContain('Cannot resolve --dirty scope')
            ->assertFailed();

        unlink($findings);
    } finally {
        removeRepository($notARepo);
    }
});

it('reports every finding when --dirty is omitted', function () {
    $repo = changedFilesRepository();

    try {
        writeRepositoryFile($repo, 'app/Models/User.php', '<?php // edited');

        useDirtyRepository($repo);

        $findings = dirtyFindingsFile([dirtyFinding('F-1', 'app/Jobs/SendMail.php')]);

        $exit = Artisan::call('auditor:report', ['--format' => 'json', '--findings' => $findings]);

        expect($exit)->toBe(0);

        $payload = json_decode(Artisan::output(), true);

        expect($payload['summary']['total_findings'])->toBe(1);
        expect($payload['meta'])->not->toHaveKey('scope');

        unlink($findings);
    } finally {
        removeRepository($repo);
    }
});
