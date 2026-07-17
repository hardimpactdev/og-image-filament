<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use HardImpact\OgImageFilament\Jobs\GenerateOgImage;
use HardImpact\OgImageFilament\OgImageFilamentPlugin;
use HardImpact\OgImageFilament\OgImageManager;
use HardImpact\OgImageFilament\Rendering\OgImageRenderer;
use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;
use Workbench\App\Filament\Resources\PostResource;
use Workbench\App\Models\Post;

beforeEach(function (): void {
    Storage::fake('public');
    config()->set('og-image-filament.disk', 'public');
    config()->set('og-image-filament.directory', 'og-images');
    config()->set('og-image-filament.node_binary');
    config()->set('og-image-filament.chrome_path');
});

it('reloads current model values and writes the deterministic path', function (): void {
    $post = Post::query()->create([
        'title' => 'Original title',
        'slug' => 'original-title',
        'summary' => 'Summary',
        'is_visible' => true,
    ]);
    $job = new GenerateOgImage('admin', PostResource::class, $post->id);

    $post->update(['title' => 'Changed after dispatch']);
    registerPostPath();
    app()->instance(
        OgImageRenderer::class,
        new OgImageRenderer(fn (string $html): Browsershot => new JobBrowsershot($html)),
    );

    $job->handle(resolve(OgImageManager::class));

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
    view()->addNamespace('test-og-images', dirname(__DIR__).'/Fixtures/views');
    registerPostPath();

    $plugin = Filament::getDefaultPanel()->getPlugin('og-image-filament');

    if (! $plugin instanceof OgImageFilamentPlugin) {
        throw new LogicException('The test panel registered an unexpected OG image plugin.');
    }

    $plugin->getSources()[PostResource::class]
        ->template('test-og-images::source-card');
    app()->instance(
        OgImageRenderer::class,
        new OgImageRenderer(fn (string $html): Browsershot => new JobBrowsershot($html)),
    );

    (new GenerateOgImage('admin', PostResource::class, $post->id))
        ->handle(resolve(OgImageManager::class));

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
    $job = new GenerateOgImage('admin', PostResource::class, $post->id);
    registerPostPath();
    app()->instance(
        OgImageRenderer::class,
        new OgImageRenderer(fn (string $html): Browsershot => new JobBrowsershot('last-good-png')),
    );
    $job->handle(resolve(OgImageManager::class));

    app()->instance(
        OgImageRenderer::class,
        new OgImageRenderer(fn (string $html): never => throw new RuntimeException('Chrome failed')),
    );

    expect(fn () => $job->handle(resolve(OgImageManager::class)))
        ->toThrow(RuntimeException::class, 'Chrome failed')
        ->and(Storage::disk('public')->get("og-images/posts/{$post->id}.png"))
        ->toBe('last-good-png');
});

function registerPostPath(): void
{
    $plugin = Filament::getDefaultPanel()->getPlugin('og-image-filament');

    if (! $plugin instanceof OgImageFilamentPlugin) {
        throw new LogicException('The test panel registered an unexpected OG image plugin.');
    }

    $plugin->getSources()[PostResource::class]
        ->pathUsing(fn (Post $post): string => "posts/{$post->id}.png");
}

final class JobBrowsershot extends Browsershot
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
