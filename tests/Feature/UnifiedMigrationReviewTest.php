<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LaravelAuditor\Context\Migrations\MigrationSourceReview;
use LaravelAuditor\Context\Snapshots\SnapshotBuilder;

it('combines selected migration source and driver questions without executing source or overwriting it', function () {
    $directory = database_path('migrations');

    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    $file = $directory.DIRECTORY_SEPARATOR.'2030_01_01_000003_unified_review.php';
    $snapshotPath = sys_get_temp_dir().'/unified-review-'.uniqid('', true).'.json';
    $marker = sys_get_temp_dir().'/unified-review-marker-'.uniqid('', true);
    $php = '<?php file_put_contents('.var_export($marker, true).', "EXECUTED"); return new class { public function up() { Schema::drop("archived_data"); } };';
    $snapshot = app(SnapshotBuilder::class)->capture();
    $snapshot['sections']['schema'] = ['driver' => 'sqlite', 'tables' => []];

    try {
        file_put_contents($file, $php);
        file_put_contents($snapshotPath, json_encode($snapshot, JSON_THROW_ON_ERROR));
        $options = ['before' => $snapshotPath, 'after' => $snapshotPath, '--migration' => [$file], '--driver-guidance' => true];

        expect(Artisan::call('auditor:review', $options))->toBe(0);
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        expect($report['coverage']['migration_source'])->toBeTrue()
            ->and($report['coverage']['driver_guidance'])->toBeTrue()
            ->and($report['summary']['migration_risk_indicators'])->toBe(0)
            ->and($report['results']['migration_risk']['source_review']['files'][0]['indicators'][0]['code'])->toBe('source_table_drop')
            ->and($report['results']['migration_risk']['driver_review']['locking_verified'])->toBeFalse()
            ->and(is_file($marker))->toBeFalse();

        expect(Artisan::call('auditor:review', [...$options, '--format' => 'markdown']))->toBe(0)
            ->and(Artisan::output())->toContain('Selected migration source (not executed)', 'source_table_drop', 'Conditional driver guidance');

        $this->artisan('auditor:review', [...$options, '--output' => $file])->assertFailed();
        expect(file_get_contents($file))->toBe($php);
    } finally {
        foreach ([$file, $snapshotPath, $marker] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
});

it('rejects optional migration review when schema evidence was not captured', function () {
    $path = sys_get_temp_dir().'/unified-review-no-schema-'.uniqid('', true).'.json';

    try {
        file_put_contents($path, json_encode(app(SnapshotBuilder::class)->capture(), JSON_THROW_ON_ERROR));
        $this->artisan('auditor:review', ['before' => $path, 'after' => $path, '--driver-guidance' => true])
            ->expectsOutputToContain('schema-enabled snapshots on both sides')
            ->assertFailed();
    } finally {
        unlink($path);
    }
});

it('accepts a migration in a separately registered directory', function () {
    $directory = sys_get_temp_dir().'/auditor-registered-migrations-'.uniqid();
    mkdir($directory);
    $file = $directory.'/2030_01_01_000004_review.php';

    try {
        file_put_contents($file, '<?php return new class { public function up() {} };');
        app('migrator')->path($directory);
        $report = app(MigrationSourceReview::class)->review([$file, $file]);

        expect($report['files'])->toHaveCount(1)
            ->and($report['files'][0]['indicators'])->toBe([]);
    } finally {
        unlink($file);
        rmdir($directory);
    }
});

it('excludes migration links whose targets are outside the registered directory', function () {
    $root = sys_get_temp_dir().'/auditor-migration-paths-'.uniqid();
    $directory = $root.'/registered';
    mkdir($directory, 0777, true);
    $file = $root.'/outside.php';
    $link = $directory.'/2030_01_01_000005_link.php';

    try {
        file_put_contents($file, '<?php return new class { public function up() {} };');

        if (! @symlink($file, $link)) {
            $this->markTestSkipped('Creating file symlinks is unavailable.');
        }
        app('migrator')->path($directory);

        expect(fn () => app(MigrationSourceReview::class)->review([$link]))
            ->toThrow(RuntimeException::class, 'not found in the registered migration paths');
    } finally {
        if (is_link($link)) {
            unlink($link);
        }
        unlink($file);
        rmdir($directory);
        rmdir($root);
    }
});
