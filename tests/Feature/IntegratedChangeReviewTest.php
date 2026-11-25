<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

it('reviews captured SQLite and application changes with Git and a regression gate', function () {
    $repo = changedFilesRepository();
    $artifacts = sys_get_temp_dir().'/auditor-integrated-'.uniqid();
    mkdir($artifacts);
    $before = $artifacts.'/before.json';
    $after = $artifacts.'/after.json';
    $baseline = $artifacts.'/baseline.json';
    $old = $artifacts.'/findings-before.json';
    $current = $artifacts.'/findings-after.json';
    $finding = [
        'rule_id' => 'AUD-SEC-001',
        'title' => 'Synthetic review finding',
        'domain' => 'security',
        'confidence' => 'medium',
        'summary' => 'Fixture for finding comparison.',
        'why_it_matters' => 'Exercises report and CI policy.',
        'evidence' => [['type' => 'file', 'reference' => 'app.php']],
    ];
    $entry = static fn (string $id, string $severity, string $status = 'open'): array => [
        ...$finding, 'id' => $id, 'severity' => $severity, 'status' => $status,
    ];

    try {
        useDirtyRepository($repo);
        writeRepositoryFile($repo, 'app.php', '<?php // before');
        runGit($repo, 'add', '.');
        runGit($repo, 'commit', '-qm', 'before');
        runGit($repo, 'branch', 'auditor-base');
        Schema::create('auditor_integrated_records', static function (Blueprint $table): void {
            $table->id();
            $table->string('legacy')->nullable();
        });
        expect(Artisan::call('auditor:snapshot', ['--schema' => true, '--with-provenance' => true, '--output' => $before]))->toBe(0);
        file_put_contents($old, json_encode([
            $entry('worse', 'medium'), $entry('reopened', 'high', 'accepted'),
            $entry('unchanged', 'info'), $entry('absent', 'high'),
        ], JSON_THROW_ON_ERROR));
        expect(Artisan::call('auditor:baseline', ['--findings' => $old, '--output' => $baseline, '--with-status' => true]))->toBe(0);

        Schema::table('auditor_integrated_records', static fn (Blueprint $table) => $table->dropColumn('legacy'));
        Gate::define('auditor-integrated', static fn (): bool => true);
        Route::get('/auditor-integrated', static fn (): string => 'fixture')->middleware('can:auditor-integrated');
        writeRepositoryFile($repo, 'app.php', '<?php // after');
        runGit($repo, 'add', '.');
        runGit($repo, 'commit', '-qm', 'after');
        expect(Artisan::call('auditor:snapshot', ['--schema' => true, '--with-provenance' => true, '--output' => $after]))->toBe(0);
        file_put_contents($current, json_encode([
            $entry('worse', 'high'), $entry('reopened', 'high'),
            $entry('unchanged', 'info'), $entry('new', 'low'),
        ], JSON_THROW_ON_ERROR));

        $options = ['before' => $before, 'after' => $after, '--base' => 'auditor-base',
            '--findings' => $current, '--baseline' => $baseline, '--driver-guidance' => true];
        $hashes = array_map(static fn (string $path) => hash_file('sha256', $path), [$before, $after, $baseline, $current]);
        expect(Artisan::call('auditor:review', $options))->toBe(0);
        $review = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        expect($review['results']['git_review']['changed_files'])->toBe(['app.php'])
            ->and($review['results']['snapshot_comparison']['summary']['routes_added'])->toBe(1)
            ->and(array_column($review['results']['migration_risk']['indicators'], 'code'))->toContain('column_removed')
            ->and($review['summary']['baseline'])->toBe(['new' => 1, 'persistent' => 3, 'absent' => 1])
            ->and(array_column($review['results']['finding_review']['baseline']['regressions'], 'id'))->toBe(['reopened', 'worse']);

        foreach (['text', 'markdown'] as $format) {
            expect(Artisan::call('auditor:review', [...$options, '--format' => $format]))->toBe(0)
                ->and(Artisan::output())->toContain('Conditional driver guidance', 'Git artifact review', '[UPDATED]', '[UNCHANGED]');
        }

        $gate = ['--findings' => $current, '--baseline' => $baseline, '--fail-on' => 'high'];
        expect(Artisan::call('auditor:ci', $gate))->toBe(0)
            ->and(Artisan::call('auditor:ci', [...$gate, '--fail-on-regression' => true]))->toBe(1)
            ->and(Artisan::call('auditor:ci', [...$gate, '--fail-on-regression' => true, '--format' => 'github']))->toBe(1)
            ->and(Artisan::output())->toContain('::error')
            ->and(Artisan::call('auditor:ci', [...$gate, '--format' => 'sarif', '--output' => $artifacts.'/report.sarif']))->toBe(0)
            ->and(json_decode((string) file_get_contents($artifacts.'/report.sarif'), true, flags: JSON_THROW_ON_ERROR)['version'])->toBe('2.1.0')
            ->and(array_map(static fn (string $path) => hash_file('sha256', $path), [$before, $after, $baseline, $current]))->toBe($hashes);
    } finally {
        Schema::dropIfExists('auditor_integrated_records');
        removeRepository($repo);
        removeRepository($artifacts);
    }
});
