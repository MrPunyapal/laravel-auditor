<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LaravelAuditor\Context\Collectors\ReviewScopeCollector;
use LaravelAuditor\Context\ContextRegistry;
use LaravelAuditor\Support\ChangedFilesOptions;
use LaravelAuditor\Support\GitStatus;
use LaravelAuditor\Support\ReviewScope;

it('registers the review scope collector', function () {
    expect(app(ContextRegistry::class)->has('review_scope'))->toBeTrue();
    expect(app(ContextRegistry::class)->get('review_scope'))->toBeInstanceOf(ReviewScopeCollector::class);
});

it('scopes an audit to dirty files and the files they use', function () {
    $repo = changedFilesRepository();

    try {
        writeRepositoryFile($repo, 'app/Livewire/Rooms/Index.php', <<<'PHP'
            <?php

            namespace App\Livewire\Rooms;

            use App\Models\Room;

            class Index
            {
                public function render()
                {
                    return view('livewire.rooms.index');
                }
            }
            PHP);
        writeRepositoryFile($repo, 'app/Models/Room.php', '<?php');
        writeRepositoryFile($repo, 'app/Models/Untouched.php', '<?php');
        writeRepositoryFile($repo, 'resources/views/livewire/rooms/index.blade.php', '<div></div>');
        writeRepositoryFile($repo, 'tests/Feature/Livewire/Rooms/IndexTest.php', '<?php');
        runGit($repo, 'add', '-A');
        runGit($repo, 'commit', '-qm', 'init');
        writeRepositoryFile($repo, 'app/Livewire/Rooms/Index.php', <<<'PHP'
            <?php

            namespace App\Livewire\Rooms;

            use App\Models\Room;

            class Index
            {
                public function render()
                {
                    return view('livewire.rooms.index'); // edited
                }
            }
            PHP);

        $scope = (new ReviewScope(new GitStatus($repo), app(ChangedFilesOptions::class), $repo))->collect();

        expect($scope['available'])->toBeTrue();
        expect($scope['mode'])->toBe('dirty');
        expect($scope['changed'])->toBe(['app/Livewire/Rooms/Index.php']);
        expect($scope['related'])->toBe([
            'app/Models/Room.php',
            'resources/views/livewire/rooms/index.blade.php',
            'tests/Feature/Livewire/Rooms/IndexTest.php',
        ]);
        expect($scope['scope'])->toBe([
            'app/Livewire/Rooms/Index.php',
            'app/Models/Room.php',
            'resources/views/livewire/rooms/index.blade.php',
            'tests/Feature/Livewire/Rooms/IndexTest.php',
        ]);
        expect($scope['truncated'])->toBeFalse();
    } finally {
        removeRepository($repo);
    }
});

it('reports a clean tree as an empty scope', function () {
    $repo = changedFilesRepository();

    try {
        writeRepositoryFile($repo, 'app/Models/Room.php', '<?php');
        runGit($repo, 'add', '-A');
        runGit($repo, 'commit', '-qm', 'init');

        $scope = (new ReviewScope(new GitStatus($repo), app(ChangedFilesOptions::class), $repo))->collect();

        expect($scope['available'])->toBeTrue();
        expect($scope['changed'])->toBe([]);
        expect($scope['related'])->toBe([]);
        expect($scope['scope'])->toBe([]);
    } finally {
        removeRepository($repo);
    }
});

it('dumps the review scope collector through artisan', function () {
    $exit = Artisan::call('auditor:context', ['collector' => 'review_scope']);

    expect($exit)->toBe(0);

    $payload = json_decode(Artisan::output(), true);

    expect($payload)->toHaveKeys(['available', 'mode', 'changed', 'related', 'scope', 'changed_count', 'related_count', 'truncated']);
});
