<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LaravelAuditor\Context\Snapshots\SnapshotBuilder;

it('reviews an explicitly selected migration source without running its PHP', function () {
    $directory = database_path('migrations');

    if (is_dir($directory) === false) {
        mkdir($directory, 0777, true);
    }

    $file = $directory.DIRECTORY_SEPARATOR.'2030_01_01_000001_auditor_source_review.php';
    $beforePath = sys_get_temp_dir().'/auditor-source-before-'.uniqid().'.json';
    $afterPath = sys_get_temp_dir().'/auditor-source-after-'.uniqid().'.json';
    $marker = sys_get_temp_dir().'/auditor-source-execution-marker-'.uniqid();
    $php = '<?php file_put_contents('.var_export($marker, true).', "EXECUTED"); return new class { public function up() { Schema::drop("archived_data"); } };';

    $snapshot = app(SnapshotBuilder::class)->capture();
    $snapshot['sections']['schema'] = ['driver' => 'sqlite', 'tables' => []];

    try {
        file_put_contents($file, $php);
        file_put_contents($beforePath, json_encode($snapshot, JSON_THROW_ON_ERROR));
        file_put_contents($afterPath, json_encode($snapshot, JSON_THROW_ON_ERROR));

        expect(Artisan::call('auditor:migration-risk', [
            'before' => $beforePath, 'after' => $afterPath, '--migration' => [$file],
        ]))->toBe(0);

        $data = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        expect($data['source_review']['files'])->toHaveCount(1)
            ->and($data['source_review']['files'][0]['indicators'][0]['code'])->toBe('source_table_drop')
            ->and($data['coverage'])->toBe('snapshot_structure_plus_selected_php_source')
            ->and(is_file($marker))->toBeFalse()
            ->and(json_encode($data))->not->toContain($marker);
    } finally {
        foreach ([$file, $beforePath, $afterPath, $marker] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
});

it('rejects unregistered source files and does not create the output', function () {
    $snapshotPath = sys_get_temp_dir().'/auditor-source-valid-'.uniqid().'.json';
    $otherPath = sys_get_temp_dir().'/auditor-source-other-'.uniqid().'.php';
    $outputPath = sys_get_temp_dir().'/auditor-source-should-not-exist-'.uniqid().'.json';
    $snapshot = app(SnapshotBuilder::class)->capture();
    $snapshot['sections']['schema'] = ['driver' => 'sqlite', 'tables' => []];

    try {
        file_put_contents($snapshotPath, json_encode($snapshot, JSON_THROW_ON_ERROR));
        file_put_contents($otherPath, '<?php return new class { public function up() {} };');

        expect(Artisan::call('auditor:migration-risk', [
            'before' => $snapshotPath, 'after' => $snapshotPath,
            '--migration' => [$otherPath], '--output' => $outputPath,
        ]))->toBe(1)
            ->and(is_file($outputPath))->toBeFalse();
    } finally {
        foreach ([$snapshotPath, $otherPath, $outputPath] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
});

it('does not allow output to overwrite a relative migration path from another working directory', function () {
    $directory = database_path('migrations');
    $file = $directory.DIRECTORY_SEPARATOR.'2030_01_01_000002_auditor_relative_source.php';
    $snapshotPath = sys_get_temp_dir().'/auditor-source-relative-'.uniqid().'.json';
    $originalCwd = getcwd();
    $php = '<?php return new class { public function up() { Schema::drop("archived_data"); } };';
    $snapshot = app(SnapshotBuilder::class)->capture();
    $snapshot['sections']['schema'] = ['driver' => 'sqlite', 'tables' => []];

    try {
        file_put_contents($file, $php);
        file_put_contents($snapshotPath, json_encode($snapshot, JSON_THROW_ON_ERROR));
        chdir(sys_get_temp_dir());

        expect(Artisan::call('auditor:migration-risk', [
            'before' => $snapshotPath,
            'after' => $snapshotPath,
            '--migration' => ['database/migrations/'.basename($file)],
            '--output' => $file,
        ]))->toBe(1)
            ->and(file_get_contents($file))->toBe($php);
    } finally {
        if (is_string($originalCwd)) {
            chdir($originalCwd);
        }

        foreach ([$file, $snapshotPath] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
});
