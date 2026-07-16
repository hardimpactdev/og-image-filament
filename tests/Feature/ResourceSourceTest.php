<?php

declare(strict_types=1);

use HardImpact\OgImageFilament\Exceptions\InvalidSourceConfiguration;
use HardImpact\OgImageFilament\Sources\ResourceSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Workbench\App\Filament\Resources\PostResource;
use Workbench\App\Models\Post;

it('uses Filament resource defaults for labels, searching, and titles', function (): void {
    $post = Post::query()->create([
        'title' => 'Searchable post',
        'slug' => 'searchable-post',
        'summary' => 'Summary',
        'is_visible' => true,
    ]);

    $source = ResourceSource::make(PostResource::class)
        ->map(fn (Post $post): array => ['title' => $post->title]);

    expect($source->getKey())->toBe(PostResource::class)
        ->and($source->getLabel())->toBe(PostResource::getPluralModelLabel())
        ->and($source->isAccessible())->toBeTrue()
        ->and($source->search('Searchable'))->toBe([
            $post->id => 'Searchable post',
        ]);
});

it('keeps resource and record authorization intact', function (): void {
    $visible = Post::query()->create([
        'title' => 'Visible post',
        'slug' => 'visible-post',
        'summary' => 'Summary',
        'is_visible' => true,
    ]);
    $hidden = Post::query()->create([
        'title' => 'Hidden post',
        'slug' => 'hidden-post',
        'summary' => 'Summary',
        'is_visible' => false,
    ]);

    $source = ResourceSource::make(PostResource::class)
        ->map(fn (Post $post): array => ['title' => $post->title]);

    expect($source->resolveRecord($visible->id))->toBeInstanceOf(Post::class)
        ->and($source->resolveRecord($hidden->id))->toBeNull()
        ->and($source->search('post'))->toHaveKey($visible->id)
        ->and($source->search('post'))->not->toHaveKey($hidden->id);
});

it('applies query, title, search, and mapper overrides', function (): void {
    $published = Post::query()->create([
        'title' => 'Published post',
        'slug' => 'published-post',
        'summary' => 'Published summary',
        'is_visible' => true,
    ]);
    Post::query()->create([
        'title' => 'Excluded post',
        'slug' => 'excluded-post',
        'summary' => 'Excluded summary',
        'is_visible' => true,
    ]);

    $source = ResourceSource::make(PostResource::class)
        ->label('Published posts')
        ->searchColumns(['slug'])
        ->recordTitle(fn (Post $post): HtmlString => new HtmlString("<strong>{$post->title}</strong> · {$post->slug}"))
        ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereKey($published->getKey()))
        ->map(fn (Post $post): array => [
            'title' => $post->title,
            'description' => $post->summary,
        ]);

    expect($source->getLabel())->toBe('Published posts')
        ->and($source->search('published'))->toBe([
            $published->id => 'Published post · published-post',
        ])
        ->and($source->search('Excluded'))->toBe([])
        ->and($source->mapRecord($published))->toBe([
            'title' => 'Published post',
            'description' => 'Published summary',
        ]);
});

it('rejects invalid resources, empty search columns, and missing mappers', function (): void {
    expect(fn () => ResourceSource::make(Post::class))
        ->toThrow(InvalidSourceConfiguration::class)
        ->and(fn () => ResourceSource::make(PostResource::class)->searchColumns([]))
        ->toThrow(InvalidSourceConfiguration::class)
        ->and(fn () => ResourceSource::make(PostResource::class)->mapRecord(new Post))
        ->toThrow(InvalidSourceConfiguration::class);
});
