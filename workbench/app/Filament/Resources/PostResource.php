<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources;

use Filament\Resources\Resource;
use Workbench\App\Models\Post;

/**
 * @extends resource<Post>
 */
final class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static ?string $recordTitleAttribute = 'title';

    /**
     * @return array<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'slug'];
    }
}
