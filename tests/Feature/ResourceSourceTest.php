<?php

declare(strict_types=1);

use HardImpact\OgImageFilament\Exceptions\InvalidSourceConfiguration;
use HardImpact\OgImageFilament\Sources\ModelValue;
use HardImpact\OgImageFilament\Sources\ResourceSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Workbench\App\Data\PostOgImageData;
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

it('resolves the configured template and DTO data', function (): void {
    $post = Post::query()->create([
        'title' => 'DTO title',
        'slug' => 'dto-title',
        'summary' => 'Summary',
        'is_visible' => true,
    ]);
    $source = ResourceSource::make(PostResource::class)
        ->template('test-og-images::source-card')
        ->dataUsing(fn (Post $post): PostOgImageData => PostOgImageData::from($post));

    expect($source->getTemplate())->toBe('test-og-images::source-card')
        ->and($source->resolveData($post))->toEqual(new PostOgImageData('DTO title'));
});

it('requires a valid resource template and DTO resolver', function (): void {
    $post = Post::query()->create([
        'title' => 'Invalid DTO',
        'slug' => 'invalid-dto',
        'summary' => 'Summary',
        'is_visible' => true,
    ]);

    expect(fn () => ResourceSource::make(PostResource::class)->getTemplate())
        ->toThrow(InvalidSourceConfiguration::class, 'must configure a Blade view')
        ->and(fn () => ResourceSource::make(PostResource::class)->template('   '))
        ->toThrow(InvalidSourceConfiguration::class, 'non-empty Blade view')
        ->and(fn () => ResourceSource::make(PostResource::class)->resolveData($post))
        ->toThrow(InvalidSourceConfiguration::class, 'must configure a data resolver')
        ->and(fn () => ResourceSource::make(PostResource::class)
            ->dataUsing(fn (Post $post): string => $post->title)
            ->resolveData($post))
        ->toThrow(InvalidSourceConfiguration::class, 'must return an object');
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

it('fills the search limit after filtering unauthorized records', function (): void {
    foreach (range(1, 55) as $index) {
        Post::query()->create([
            'title' => "Matching hidden post {$index}",
            'slug' => "matching-hidden-post-{$index}",
            'summary' => 'Summary',
            'is_visible' => false,
        ]);
    }

    $visible = Post::query()->create([
        'title' => 'Matching visible post',
        'slug' => 'matching-visible-post',
        'summary' => 'Summary',
        'is_visible' => true,
    ]);

    $source = ResourceSource::make(PostResource::class)
        ->modifyQueryUsing(fn (Builder $query): Builder => $query->orderBy('id'))
        ->map(fn (Post $post): array => ['title' => $post->title]);

    expect($source->search('Matching'))->toBe([
        $visible->id => 'Matching visible post',
    ]);
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

it('discovers model columns for visual mappings', function (): void {
    expect(ResourceSource::make(PostResource::class)->getModelColumns())
        ->toMatchArray([
            'id' => 'Id',
            'title' => 'Title',
            'slug' => 'Slug',
            'summary' => 'Summary',
            'is_visible' => 'Is Visible',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ]);
});

it('stores serializable default mappings', function (): void {
    $source = ResourceSource::make(PostResource::class)
        ->defaultMappings([
            'label' => ['source' => 'static', 'value' => 'Post'],
            'title' => ['source' => 'column', 'value' => 'title'],
        ]);

    expect($source->getDefaultMappings())->toBe([
        'label' => ['source' => 'static', 'value' => 'Post'],
        'title' => ['source' => 'column', 'value' => 'title'],
    ]);
});

it('registers and resolves named model values', function (): void {
    $post = Post::query()->create([
        'title' => 'Named value',
        'slug' => 'named-value',
        'summary' => 'Summary',
        'is_visible' => true,
    ]);
    $source = ResourceSource::make(PostResource::class)
        ->modelValues([
            ModelValue::make('seo_title')
                ->label('SEO title')
                ->resolveUsing(fn (Post $post): string => "{$post->title} SEO"),
        ]);

    expect($source->getModelValueOptions())->toBe([
        'seo_title' => 'SEO title',
    ])->and($source->resolveModelValue('seo_title', $post))->toBe('Named value SEO');
});

it('rejects duplicate named model values', function (): void {
    expect(fn () => ResourceSource::make(PostResource::class)->modelValues([
        ModelValue::make('seo_title')->resolveUsing(fn (Post $post): string => $post->title),
        ModelValue::make('seo_title')->resolveUsing(fn (Post $post): string => $post->title),
    ]))->toThrow(InvalidSourceConfiguration::class);
});

it('resolves deterministic relative image paths', function (): void {
    $post = Post::query()->create([
        'title' => 'Path post',
        'slug' => 'path-post',
        'summary' => 'Summary',
        'is_visible' => true,
    ]);
    $source = ResourceSource::make(PostResource::class)
        ->pathUsing(fn (Post $post): string => "posts/{$post->id}.png");

    expect($source->resolvePath($post))->toBe("posts/{$post->id}.png")
        ->and(fn () => ResourceSource::make(PostResource::class)
            ->pathUsing(fn (): string => '../outside.png')
            ->resolvePath($post))
        ->toThrow(InvalidSourceConfiguration::class);
});

it('rejects invalid serializable mappings', function (): void {
    expect(fn () => ResourceSource::make(PostResource::class)->defaultMappings([
        'title' => ['source' => 'computed', 'value' => 'title'],
    ]))->toThrow(InvalidSourceConfiguration::class);
});
