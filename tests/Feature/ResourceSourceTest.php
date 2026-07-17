<?php

declare(strict_types=1);

use HardImpact\OgImageFilament\Exceptions\InvalidSourceConfiguration;
use HardImpact\OgImageFilament\Sources\ResourceSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Workbench\App\Data\PostOgImageData;
use Workbench\App\Filament\Resources\PostResource;
use Workbench\App\Models\Post;

it('uses Filament resource defaults for labels, searching, and titles', function (): void {
    $post = sourcePost('Searchable post', visible: true);
    $source = ResourceSource::make(PostResource::class);

    expect($source->getKey())->toBe(PostResource::class)
        ->and($source->getLabel())->toBe(PostResource::getPluralModelLabel())
        ->and($source->isAccessible())->toBeTrue()
        ->and($source->search('Searchable'))->toBe([
            $post->id => 'Searchable post',
        ]);
});

it('resolves the configured template and DTO data', function (): void {
    $post = sourcePost('DTO title', visible: true);
    $source = ResourceSource::make(PostResource::class)
        ->template('test-og-images::source-card')
        ->dataUsing(fn (Post $post): PostOgImageData => PostOgImageData::from($post))
        ->pathUsing(fn (Post $post): string => "posts/{$post->id}.png");

    $source->assertConfigured();

    expect($source->getTemplate())->toBe('test-og-images::source-card')
        ->and($source->resolveData($post))->toEqual(new PostOgImageData('DTO title'));
});

it('requires a template, DTO resolver, and path resolver', function (): void {
    $post = sourcePost('Invalid DTO', visible: true);

    expect(fn () => ResourceSource::make(PostResource::class)->assertConfigured())
        ->toThrow(InvalidSourceConfiguration::class, 'must configure a Blade view')
        ->and(fn () => ResourceSource::make(PostResource::class)->template('   '))
        ->toThrow(InvalidSourceConfiguration::class, 'non-empty Blade view')
        ->and(fn () => ResourceSource::make(PostResource::class)
            ->template('test-og-images::source-card')
            ->assertConfigured())
        ->toThrow(InvalidSourceConfiguration::class, 'must configure a data resolver')
        ->and(fn () => ResourceSource::make(PostResource::class)
            ->template('test-og-images::source-card')
            ->dataUsing(fn (Post $post): PostOgImageData => PostOgImageData::from($post))
            ->assertConfigured())
        ->toThrow(InvalidSourceConfiguration::class, 'deterministic path resolver')
        ->and(fn () => ResourceSource::make(PostResource::class)
            ->dataUsing(fn (Post $post): string => $post->title)
            ->resolveData($post))
        ->toThrow(InvalidSourceConfiguration::class, 'must return an object');
});

it('keeps resource and record authorization intact', function (): void {
    $visible = sourcePost('Visible post', visible: true);
    $hidden = sourcePost('Hidden post', visible: false);
    $source = ResourceSource::make(PostResource::class);

    expect($source->resolveRecord($visible->id))->toBeInstanceOf(Post::class)
        ->and($source->resolveRecord($hidden->id))->toBeNull()
        ->and($source->search('post'))->toHaveKey($visible->id)
        ->and($source->search('post'))->not->toHaveKey($hidden->id);
});

it('fills the search limit after filtering unauthorized records', function (): void {
    foreach (range(1, 55) as $index) {
        sourcePost("Matching hidden post {$index}", visible: false);
    }

    $visible = sourcePost('Matching visible post', visible: true);
    $source = ResourceSource::make(PostResource::class)
        ->modifyQueryUsing(fn (Builder $query): Builder => $query->orderBy('id'));

    expect($source->search('Matching'))->toBe([
        $visible->id => 'Matching visible post',
    ]);
});

it('applies query, title, and search overrides', function (): void {
    $published = sourcePost('Published post', visible: true);
    sourcePost('Excluded post', visible: true);

    $source = ResourceSource::make(PostResource::class)
        ->label('Published posts')
        ->searchColumns(['slug'])
        ->recordTitle(fn (Post $post): HtmlString => new HtmlString("<strong>{$post->title}</strong> · {$post->slug}"))
        ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereKey($published->getKey()));

    expect($source->getLabel())->toBe('Published posts')
        ->and($source->search('published'))->toBe([
            $published->id => 'Published post · published-post',
        ])
        ->and($source->search('Excluded'))->toBe([]);
});

it('resolves deterministic relative image paths', function (): void {
    $post = sourcePost('Path post', visible: true);
    $source = ResourceSource::make(PostResource::class)
        ->pathUsing(fn (Post $post): string => "posts/{$post->id}.png");

    expect($source->resolvePath($post))->toBe("posts/{$post->id}.png")
        ->and(fn () => ResourceSource::make(PostResource::class)
            ->pathUsing(fn (): string => '../outside.png')
            ->resolvePath($post))
        ->toThrow(InvalidSourceConfiguration::class);
});

it('rejects invalid resources and empty search columns', function (): void {
    expect(fn () => ResourceSource::make(Post::class))
        ->toThrow(InvalidSourceConfiguration::class)
        ->and(fn () => ResourceSource::make(PostResource::class)->searchColumns([]))
        ->toThrow(InvalidSourceConfiguration::class);
});

function sourcePost(string $title, bool $visible): Post
{
    return Post::query()->create([
        'title' => $title,
        'slug' => str($title)->slug()->toString(),
        'summary' => fake()->sentence(),
        'is_visible' => $visible,
    ]);
}
