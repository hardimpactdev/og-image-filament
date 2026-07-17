<?php

declare(strict_types=1);

namespace Workbench\App\Data;

use Workbench\App\Models\Post;

final readonly class PostOgImageData
{
    public function __construct(public string $title) {}

    public static function from(Post $post): self
    {
        return new self($post->title);
    }
}
