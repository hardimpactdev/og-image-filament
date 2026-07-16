<?php

declare(strict_types=1);

namespace Workbench\App\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Workbench\App\Models\Post;

final class PostPolicy
{
    public function viewAny(?Authenticatable $user): bool
    {
        return true;
    }

    public function view(?Authenticatable $user, Post $post): bool
    {
        return $post->is_visible;
    }
}
