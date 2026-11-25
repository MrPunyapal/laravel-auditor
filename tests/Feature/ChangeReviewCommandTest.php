<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LaravelAuditor\Audit\Findings\FindingBaseline;
use LaravelAuditor\Audit\Findings\FindingLoader;
use LaravelAuditor\Context\Snapshots\SnapshotBuilder;

function changeReviewTempFile(array $payload): string
{
    $path = sys_get_temp_dir().'/laravel-auditor-review-'.uniqid('', true).'.json';
    file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR));

    return $path;
}

it('combines route snapshot evidence without inventing security findings', function () {
    $before = app(SnapshotBuilder::class)->capture();
    $after = $before;
    $after['sections']['routes'][0]['middleware'][] = 'can:update,post';
    $beforePath = changeReviewTempFile($before);
    $afterPath = changeReviewTempFile($after);

    try {
        expect(Artisan::call('auditor:review', ['before' => $beforePath, 'after' => $afterPath]))->toBe(0);

        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        expect($report['format'])->toBe('laravel-auditor-change-review')
            ->and($report['version'])->toBe(1)
            ->and($report['summary']['structural_changes_present'])->toBeTrue()
            ->and($report['results']['snapshot_comparison']['summary']['routes_modified'])->toBe(1)
            ->and($report['coverage']['database_schema'])->toBeFalse()
            ->and($report['results']['migration_risk'])->toBeNull()
            ->and($report['results']['finding_review'])->toBeNull()
            ->and($report['summary']['findings_count'])->toBeNull();
    } finally {
        unlink($beforePath);
        unlink($afterPath);
    }
});

it('includes structural migration risks only with complete schema snapshots', function () {
    $before = app(SnapshotBuilder::class)->capture();
    $before['sections']['schema'] = ['driver' => 'sqlite', 'tables' => [[
        'name' => 'orders',
        'schema' => null,
        'columns' => [['name' => 'id', 'type' => 'integer', 'nullable' => false],
            ['name' => 'legacy', 'type' => 'varchar', 'nullable' => true]],
        'indexes' => [], 'foreign_keys' => [],
    ]]];
    $after = $before;
    array_pop($after['sections']['schema']['tables'][0]['columns']);
    $beforePath = changeReviewTempFile($before);
    $afterPath = changeReviewTempFile($after);

    try {
        expect(Artisan::call('auditor:review', ['before' => $beforePath, 'after' => $afterPath]))->toBe(0);

        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        expect($report['coverage']['database_schema'])->toBeTrue()
            ->and($report['summary']['migration_risk_indicators'])->toBe(1)
            ->and($report['results']['migration_risk']['indicators'][0]['code'])->toBe('column_removed')
            ->and($report['results']['migration_risk']['deployment_safety_verified'])->toBeFalse()
            ->and($report['results']['finding_review'])->toBeNull();
    } finally {
        unlink($beforePath);
        unlink($afterPath);
    }
});

it('includes existing finding baseline comparison without performing an audit', function () {
    $snapshot = app(SnapshotBuilder::class)->capture();
    $snapshotPath = changeReviewTempFile($snapshot);
    $finding = [
        'id' => 'R-1',
        'rule_id' => 'AUD-SEC-001',
        'title' => 'Review authorization',
        'domain' => 'security',
        'severity' => 'high',
        'confidence' => 'medium',
        'status' => 'open',
        'summary' => 'A route needs authorization review.',
        'why_it_matters' => 'An unauthorized action could expose records.',
        'evidence' => [['type' => 'route', 'reference' => 'POST /orders']],
    ];
    $oldPath = changeReviewTempFile([[...$finding, 'severity' => 'medium', 'status' => 'accepted']]);
    $currentPath = changeReviewTempFile([$finding, [...$finding, 'id' => 'R-2', 'title' => 'New finding']]);
    $baselinePath = sys_get_temp_dir().'/laravel-auditor-review-baseline-'.uniqid('', true).'.json';

    try {
        file_put_contents(
            $baselinePath,
            FindingBaseline::fromFindings(app(FindingLoader::class)->load($oldPath), withStatus: true)->toJson(),
        );

        $options = [
            'before' => $snapshotPath,
            'after' => $snapshotPath,
            '--findings' => $currentPath,
            '--baseline' => $baselinePath,
        ];
        expect(Artisan::call('auditor:review', $options))->toBe(0);

        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        expect($report['summary']['findings_count'])->toBe(2)
            ->and($report['summary']['baseline'])->toBe(['new' => 1, 'persistent' => 1, 'absent' => 0])
            ->and(array_column($report['results']['finding_review']['baseline']['new'], 'id'))->toBe(['R-2'])
            ->and($report['results']['finding_review']['baseline']['regressions'][0]['reopened'])->toBeTrue()
            ->and($report['coverage']['baseline_compared'])->toBeTrue();
        foreach (['markdown', 'text'] as $format) {
            expect(Artisan::call('auditor:review', [...$options, '--format' => $format]))->toBe(0)
                ->and(Artisan::output())->toContain('[UPDATED]', 'Regression:', 'severity increased', 'reopened from');
        }
    } finally {
        foreach ([$snapshotPath, $oldPath, $currentPath, $baselinePath] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
});

it('fails closed on an incomplete input or baseline without current findings', function () {
    $snapshot = app(SnapshotBuilder::class)->capture();
    $snapshotPath = changeReviewTempFile($snapshot);
    $invalidPath = changeReviewTempFile(['format' => 'invalid']);
    $baselinePath = changeReviewTempFile([
        'format' => FindingBaseline::FORMAT,
        'version' => FindingBaseline::VERSION,
        'findings' => [],
    ]);

    try {
        expect(Artisan::call('auditor:review', [
            'before' => $snapshotPath,
            'after' => $invalidPath,
        ]))->toBe(1);

        expect(Artisan::call('auditor:review', [
            'before' => $snapshotPath,
            'after' => $snapshotPath,
            '--baseline' => $baselinePath,
        ]))->toBe(1);
    } finally {
        unlink($snapshotPath);
        unlink($invalidPath);
        unlink($baselinePath);
    }
});

it('prevents output from overwriting snapshot, finding, or baseline input', function () {
    $snapshot = app(SnapshotBuilder::class)->capture();
    $snapshotPath = changeReviewTempFile($snapshot);
    $original = file_get_contents($snapshotPath);

    try {
        expect(Artisan::call('auditor:review', [
            'before' => $snapshotPath,
            'after' => $snapshotPath,
            '--output' => $snapshotPath,
        ]))->toBe(1);

        expect(file_get_contents($snapshotPath))->toBe($original);
    } finally {
        unlink($snapshotPath);
    }
});

it('renders markdown and text from the same evidence without changing the default JSON output', function () {
    $before = app(SnapshotBuilder::class)->capture();
    $after = $before;
    $after['sections']['routes'][0]['resolved_middleware'][] = 'example-middleware';
    $beforePath = changeReviewTempFile($before);
    $afterPath = changeReviewTempFile($after);

    try {
        $options = ['before' => $beforePath, 'after' => $afterPath];
        expect(Artisan::call('auditor:review', $options))->toBe(0);
        $default = Artisan::output();

        expect(Artisan::call('auditor:review', [...$options, '--format' => 'json']))->toBe(0)
            ->and(Artisan::output())->toBe($default);

        expect(Artisan::call('auditor:review', [...$options, '--format' => 'markdown']))->toBe(0);
        $markdown = Artisan::output();
        expect($markdown)->toContain('# Laravel Auditor Change Review', '## Coverage', 'Modified routes: 1', 'example-middleware', 'Not reviewed: schema-enabled snapshots were not supplied.', 'Not reviewed: no findings file was supplied.', 'Deployment safety has not been verified.');

        expect(Artisan::call('auditor:review', [...$options, '--format' => 'markdown']))->toBe(0)
            ->and(Artisan::output())->toBe($markdown);

        expect(Artisan::call('auditor:review', [...$options, '--format' => 'text']))->toBe(0)
            ->and(Artisan::output())->toContain('Laravel Auditor Change Review', 'Modified routes: 1')
            ->not->toContain('# Laravel Auditor Change Review');

        $this->artisan('auditor:review', [...$options, '--format' => 'unsupported'])
            ->expectsOutputToContain('Unknown review format')
            ->assertFailed();
    } finally {
        unlink($beforePath);
        unlink($afterPath);
    }
});

it('keeps untrusted snapshot metadata inside a single escaped markdown code span and escapes terminal controls', function () {
    $before = app(SnapshotBuilder::class)->capture();
    $after = $before;
    $after['sections']['routes'][0]['action'] = "injected\x1b[31m\n## forged";
    $after['sections']['routes'][0]['name'] = 'bad'.chr(96).'<script>alert(1)</script>';
    $beforePath = changeReviewTempFile($before);
    $afterPath = changeReviewTempFile($after);

    try {
        $options = ['before' => $beforePath, 'after' => $afterPath];

        expect(Artisan::call('auditor:review', [...$options, '--format' => 'markdown']))->toBe(0);
        $markdown = Artisan::output();
        expect($markdown)->toContain('bad\u0060<script>alert(1)</script>', 'injected\u001b[31m\n## forged')
            ->not->toContain(chr(27), "\n## forged", 'bad'.chr(96).'<script>');

        expect(Artisan::call('auditor:review', [...$options, '--format' => 'text']))->toBe(0)
            ->and(Artisan::output())->not->toContain(chr(27), "\n## forged");
    } finally {
        unlink($beforePath);
        unlink($afterPath);
    }
});

it('writes a markdown report without overwriting inputs and publishes the review envelope schema', function () {
    $snapshot = app(SnapshotBuilder::class)->capture();
    $snapshotPath = changeReviewTempFile($snapshot);
    $outputPath = sys_get_temp_dir().'/laravel-auditor-review-'.uniqid('', true).'.md';

    try {
        $options = ['before' => $snapshotPath, 'after' => $snapshotPath, '--format' => 'markdown'];
        $this->artisan('auditor:review', [...$options, '--output' => $outputPath])->assertSuccessful();
        expect(file_get_contents($outputPath))->toStartWith('# Laravel Auditor Change Review');

        $original = file_get_contents($snapshotPath);
        $this->artisan('auditor:review', [...$options, '--output' => $snapshotPath])->assertFailed();
        expect(file_get_contents($snapshotPath))->toBe($original);

        $schema = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/resources/auditor/schema/change-review.schema.json'), true, flags: JSON_THROW_ON_ERROR);
        expect(Artisan::call('auditor:review', ['before' => $snapshotPath, 'after' => $snapshotPath]))->toBe(0);
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        expect(array_keys($report))->toBe($schema['required'])
            ->and($report['format'])->toBe($schema['properties']['format']['const'])
            ->and($report['version'])->toBe($schema['properties']['version']['const']);
    } finally {
        unlink($snapshotPath);

        if (is_file($outputPath)) {
            unlink($outputPath);
        }
    }
});
