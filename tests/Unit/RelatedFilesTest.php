<?php

declare(strict_types=1);

use LaravelAuditor\Support\RelatedFiles;

/**
 * @param  array<string, string>  $files
 */
function relatedFilesRoot(array $files): string
{
    $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'laravel-auditor-related-'.uniqid();

    foreach ($files as $path => $contents) {
        writeRepositoryFile($root, $path, $contents);
    }

    return $root;
}

it('keeps the view, imported class, and test a changed Livewire component uses', function () {
    $root = relatedFilesRoot([
        'app/Livewire/Rooms/Index.php' => <<<'PHP'
            <?php

            namespace App\Livewire\Rooms;

            use App\Models\Room;
            use App\Models\{Member, Role};

            class Index
            {
                public function render()
                {
                    return view('livewire.rooms.index');
                }
            }
            PHP,
        'app/Models/Room.php' => '<?php',
        'app/Models/Member.php' => '<?php',
        'app/Models/Role.php' => '<?php',
        'app/Models/Untouched.php' => '<?php',
        'resources/views/livewire/rooms/index.blade.php' => '<div></div>',
        'resources/views/pages/home.blade.php' => '<livewire:rooms.index />',
        'tests/Feature/Livewire/Rooms/IndexTest.php' => '<?php',
    ]);

    try {
        $related = (new RelatedFiles)->for($root, ['app/Livewire/Rooms/Index.php']);

        expect($related)->toBe([
            'app/Models/Member.php',
            'app/Models/Role.php',
            'app/Models/Room.php',
            'resources/views/livewire/rooms/index.blade.php',
            'resources/views/pages/home.blade.php',
            'tests/Feature/Livewire/Rooms/IndexTest.php',
        ]);
    } finally {
        removeRepository($root);
    }
});

it('points a changed Livewire view back at its class', function () {
    $root = relatedFilesRoot([
        'app/Livewire/Chats/SaveChat.php' => '<?php',
        'resources/views/livewire/chats/save-chat.blade.php' => '<div></div>',
    ]);

    try {
        expect((new RelatedFiles)->for($root, ['resources/views/livewire/chats/save-chat.blade.php']))
            ->toBe(['app/Livewire/Chats/SaveChat.php']);
    } finally {
        removeRepository($root);
    }
});

it('drops related paths under an ignored prefix', function () {
    $root = relatedFilesRoot([
        'app/Http/Controllers/PostController.php' => <<<'PHP'
            <?php

            namespace App\Http\Controllers;

            use Vendor\Package\Client;

            class PostController
            {
            }
            PHP,
        'composer.json' => json_encode([
            'autoload' => [
                'psr-4' => [
                    'App\\' => 'app/',
                    'Vendor\\Package\\' => 'vendor/package/src/',
                ],
            ],
        ], JSON_THROW_ON_ERROR),
        'vendor/package/src/Client.php' => '<?php',
    ]);

    try {
        expect((new RelatedFiles)->for($root, ['app/Http/Controllers/PostController.php'], ['vendor']))
            ->toBe([]);
    } finally {
        removeRepository($root);
    }
});
