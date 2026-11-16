<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LaravelAuditor\Context\Snapshots\SnapshotBuilder;

it('reports migration risk indicators from saved schema snapshots', function () {
    $beforePath = sys_get_temp_dir().'/auditor-risk-before-'.uniqid().'.json';
    $afterPath = sys_get_temp_dir().'/auditor-risk-after-'.uniqid().'.json';
    $outputPath = sys_get_temp_dir().'/auditor-risk-result-'.uniqid().'.json';

    $before = app(SnapshotBuilder::class)->capture();
    $before['sections']['schema'] = [
        'driver' => 'sqlite',
        'tables' => [[
            'name' => 'posts', 'schema' => null,
            'columns' => [['name' => 'id', 'type' => 'integer', 'nullable' => false], ['name' => 'notes', 'type' => 'text', 'nullable' => true]],
            'indexes' => [], 'foreign_keys' => [],
        ]],
    ];
    $after = $before;
    array_pop($after['sections']['schema']['tables'][0]['columns']);

    try {
        file_put_contents($beforePath, json_encode($before, JSON_THROW_ON_ERROR));
        file_put_contents($afterPath, json_encode($after, JSON_THROW_ON_ERROR));

        expect(Artisan::call('auditor:migration-risk', ['before' => $beforePath, 'after' => $afterPath]))->toBe(0);

        $result = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        expect($result['has_indicators'])->toBeTrue()
            ->and($result['deployment_safety_verified'])->toBeFalse()
            ->and($result['indicators'][0]['code'])->toBe('column_removed');

        expect(Artisan::call('auditor:migration-risk', [
            'before' => $beforePath, 'after' => $afterPath, '--output' => $outputPath,
        ]))->toBe(0)
            ->and(json_decode((string) file_get_contents($outputPath), true)['indicators'])->toBe($result['indicators']);
    } finally {
        foreach ([$beforePath, $afterPath, $outputPath] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
});

it('rejects snapshots that do not contain complete schema data', function () {
    $beforePath = sys_get_temp_dir().'/auditor-risk-incomplete-'.uniqid().'.json';
    $snapshot = app(SnapshotBuilder::class)->capture();

    try {
        file_put_contents($beforePath, json_encode($snapshot, JSON_THROW_ON_ERROR));

        expect(Artisan::call('auditor:migration-risk', ['before' => $beforePath, 'after' => $beforePath]))
            ->toBe(1);
    } finally {
        if (is_file($beforePath)) {
            unlink($beforePath);
        }
    }
});

it('refuses missing input without creating an output', function () {
    $missing = sys_get_temp_dir().'/auditor-risk-missing-'.uniqid().'.json';
    $output = sys_get_temp_dir().'/auditor-risk-not-created-'.uniqid().'.json';

    expect(Artisan::call('auditor:migration-risk', [
        'before' => $missing, 'after' => $missing, '--output' => $output,
    ]))->toBe(1)
        ->and(is_file($output))->toBeFalse();
});

it('refuses an output path that resolves to either input snapshot', function () {
    $beforePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'auditor-risk-before-'.uniqid().'.json';
    $afterPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'auditor-risk-after-'.uniqid().'.json';
    $before = app(SnapshotBuilder::class)->capture();
    $before['sections']['schema'] = ['driver' => 'sqlite', 'tables' => []];
    $after = $before;
    $beforeContent = json_encode($before, JSON_THROW_ON_ERROR);
    $afterContent = json_encode($after, JSON_THROW_ON_ERROR);

    try {
        file_put_contents($beforePath, $beforeContent);
        file_put_contents($afterPath, $afterContent);

        foreach ([
            [$beforePath, $beforePath, $beforeContent],
            [$afterPath, dirname($afterPath).DIRECTORY_SEPARATOR.'.'.DIRECTORY_SEPARATOR.basename($afterPath), $afterContent],
        ] as [$inputPath, $outputPath, $expectedContent]) {
            expect(Artisan::call('auditor:migration-risk', [
                'before' => $beforePath,
                'after' => $afterPath,
                '--output' => $outputPath,
            ]))->toBe(1)
                ->and(file_get_contents($inputPath))->toBe($expectedContent);
        }

        $symlinkPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'auditor-risk-before-link-'.uniqid().'.json';

        if (function_exists('symlink') && @symlink($beforePath, $symlinkPath)) {
            try {
                expect(Artisan::call('auditor:migration-risk', [
                    'before' => $beforePath,
                    'after' => $afterPath,
                    '--output' => $symlinkPath,
                ]))->toBe(1)
                    ->and(file_get_contents($beforePath))->toBe($beforeContent);
            } finally {
                unlink($symlinkPath);
            }
        }

        $hardLinkPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'auditor-risk-before-hard-link-'.uniqid().'.json';

        if (function_exists('link') && @link($beforePath, $hardLinkPath)) {
            try {
                expect(Artisan::call('auditor:migration-risk', [
                    'before' => $beforePath,
                    'after' => $afterPath,
                    '--output' => $hardLinkPath,
                ]))->toBe(1)
                    ->and(file_get_contents($beforePath))->toBe($beforeContent);
            } finally {
                unlink($hardLinkPath);
            }
        }
    } finally {
        foreach ([$beforePath, $afterPath] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
});
