<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LaravelAuditor\Context\Snapshots\SnapshotBuilder;
use LaravelAuditor\Support\GitStatus;

it('reviews explicit clean artifacts matching the committed merge base and head', function () {
    $repo = committedFeatureRepository();
    $beforePath = sys_get_temp_dir().'/git-review-before-'.uniqid('', true).'.json';
    $afterPath = sys_get_temp_dir().'/git-review-after-'.uniqid('', true).'.json';

    try {
        useDirtyRepository($repo);
        $git = app(GitStatus::class);
        $head = $git->revision();
        expect($head)->not->toBeNull();
        $mergeBase = $git->mergeBase('main', (string) $head);
        expect($mergeBase)->not->toBeNull()->not->toBe($head);
        $before = app(SnapshotBuilder::class)->capture();
        $before['provenance'] = [
            'revision' => $mergeBase,
            'dirty' => false,
            'routes_cached' => false,
            'php_version' => PHP_VERSION,
            'laravel_version' => '13.0.0',
        ];
        $after = $before;
        $after['provenance']['revision'] = $head;
        file_put_contents($beforePath, json_encode($before, JSON_THROW_ON_ERROR));
        file_put_contents($afterPath, json_encode($after, JSON_THROW_ON_ERROR));
        $options = ['before' => $beforePath, 'after' => $afterPath, '--base' => 'main'];

        expect(Artisan::call('auditor:review', $options))->toBe(0);
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        expect($report['results']['git_review']['merge_base'])->toBe($mergeBase)
            ->and($report['results']['git_review']['head'])->toBe($head)
            ->and($report['results']['git_review']['changed_files'])->toBe(['app/Models/User.php'])
            ->and($report['results']['git_review']['artifact_authenticity_verified'])->toBeFalse();

        $this->artisan('auditor:review', [...$options, '--base' => 'not-a-ref'])->assertFailed();

        $after['provenance']['revision'] = $mergeBase;
        file_put_contents($afterPath, json_encode($after, JSON_THROW_ON_ERROR));
        $this->artisan('auditor:review', $options)->expectsOutputToContain('matching the merge base and HEAD')->assertFailed();

        $after['provenance']['revision'] = $head;
        file_put_contents($afterPath, json_encode($after, JSON_THROW_ON_ERROR));
        writeRepositoryFile($repo, 'app/Models/User.php', '<?php // dirty');
        $this->artisan('auditor:review', $options)->expectsOutputToContain('clean working tree')->assertFailed();
    } finally {
        foreach ([$beforePath, $afterPath] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        removeRepository($repo);
    }
});
