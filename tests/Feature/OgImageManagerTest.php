<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use HardImpact\OgImageFilament\OgImageFilamentPlugin;
use HardImpact\OgImageFilament\OgImageManager;
use HardImpact\OgImageFilament\Rendering\OgImageRenderer;
use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;
use Workbench\App\Data\PostOgImageData;
use Workbench\App\Filament\Resources\PostResource;
use Workbench\App\Models\Post;

beforeEach(function (): void {
    Storage::fake('public');
    config()->set('og-image-filament.disk', 'public');
    config()->set('og-image-filament.directory', 'og-images');
    config()->set('og-image-filament.node_binary');
    config()->set('og-image-filament.chrome_path');
    view()->addNamespace('test-og-images', dirname(__DIR__).'/Fixtures/views');
});

it('reloads current model values and writes the deterministic path', function (): void {
    $post = Post::query()->create([
        'title' => 'Original title',
        'slug' => 'original-title',
        'summary' => 'Summary',
        'is_visible' => true,
    ]);
    $post->update(['title' => 'Changed after dispatch']);
    registerPostSource();
    app()->instance(
        OgImageRenderer::class,
        new OgImageRenderer(fn (string $html): Browsershot => new ManagerBrowsershot($html)),
    );

    resolve(OgImageManager::class)->generate('admin', PostResource::class, $post->id);

    $path = "og-images/posts/{$post->id}.png";
    Storage::disk('public')->assertExists($path);

    expect(Storage::disk('public')->get($path))->toContain('Changed after dispatch')
        ->and(resolve(OgImageManager::class)->url('admin', PostResource::class, $post))
        ->toEndWith("/storage/{$path}");
});

it('renders the template configured for the resource source', function (): void {
    $post = Post::query()->create([
        'title' => 'Source-specific title',
        'slug' => 'source-specific-title',
        'summary' => 'Summary',
        'is_visible' => true,
    ]);
    registerPostSource();
    app()->instance(
        OgImageRenderer::class,
        new OgImageRenderer(fn (string $html): Browsershot => new ManagerBrowsershot($html)),
    );

    resolve(OgImageManager::class)->generate('admin', PostResource::class, $post->id);

    expect(Storage::disk('public')->get("og-images/posts/{$post->id}.png"))
        ->toContain('source-template: Source-specific title');
});

it('keeps the last good image when rendering fails', function (): void {
    $post = Post::query()->create([
        'title' => 'Stable title',
        'slug' => 'stable-title',
        'summary' => 'Summary',
        'is_visible' => true,
    ]);
    registerPostSource();
    app()->instance(
        OgImageRenderer::class,
        new OgImageRenderer(fn (string $html): Browsershot => new ManagerBrowsershot('last-good-png')),
    );
    resolve(OgImageManager::class)->generate('admin', PostResource::class, $post->id);

    app()->instance(
        OgImageRenderer::class,
        new OgImageRenderer(fn (string $html): never => throw new RuntimeException('Chrome failed')),
    );

    expect(fn () => resolve(OgImageManager::class)
        ->generate('admin', PostResource::class, $post->id))
        ->toThrow(RuntimeException::class, 'Chrome failed')
        ->and(Storage::disk('public')->get("og-images/posts/{$post->id}.png"))
        ->toBe('last-good-png');
});

function registerPostSource(): void
{
    $plugin = Filament::getDefaultPanel()->getPlugin('og-image-filament');

    if (! $plugin instanceof OgImageFilamentPlugin) {
        throw new LogicException('The test panel registered an unexpected OG image plugin.');
    }

    $plugin->getSources()[PostResource::class]
        ->template('test-og-images::source-card')
        ->dataUsing(fn (Post $post): PostOgImageData => PostOgImageData::from($post))
        ->pathUsing(fn (Post $post): string => "posts/{$post->id}.png");
}

final class ManagerBrowsershot extends Browsershot
{
    public function __construct(private readonly string $png)
    {
        parent::__construct();
    }

    public function save(string $targetPath): void
    {
        file_put_contents($targetPath, $this->png);
    }
}
