<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller;
use Workbench\App\Models\Post;

class PostController extends Controller
{
    use AuthorizesRequests;

    public function index(): string
    {
        $this->authorize('viewAny', Post::class);

        return 'posts';
    }

    public function show(Post $post): string
    {
        $this->authorize('view', $post);

        return $post->title;
    }
}
