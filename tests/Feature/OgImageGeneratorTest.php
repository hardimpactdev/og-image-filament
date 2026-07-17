<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use HardImpact\OgImageFilament\Filament\Pages\OgImageGenerator;
use HardImpact\OgImageFilament\OgImageFilamentPlugin;
use HardImpact\OgImageFilament\Rendering\OgImageRenderer;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\LivewireManager;
use Spatie\Browsershot\Browsershot;
use Workbench\App\Data\PostOgImageData;
use Workbench\App\Filament\Resources\PostResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

use function Pest\Laravel\get;

it('requires panel authentication', function (): void {
    get('/admin/og-image-generator')
        ->assertRedirect('/admin/login');
});

it('shows only source and entry selection before an entry is selected', function (): void {
    app(LivewireManager::class)->actingAs(generatorUser())
        ->test(OgImageGenerator::class)
        ->assertSee('Source')
        ->assertSee('Entry')
        ->assertDontSee('Configure')
        ->assertDontSee('Properties')
        ->assertDontSee('Save configuration')
        ->assertDontSee('data-og-preview', escape: false);
});

it('renders the selected source DTO in its read-only preview', function (): void {
    $post = generatorPost('Preview DTO title');
    registerGeneratorSource();

    app(LivewireManager::class)->actingAs(generatorUser())
        ->test(OgImageGenerator::class)
        ->fillForm([
            'source' => PostResource::class,
            'entry' => $post->id,
        ])
        ->assertSee('data-og-preview', escape: false)
        ->assertSee('source-template: Preview DTO title');
});

it('regenerates the selected OG image synchronously', function (): void {
    Storage::fake('public');
    Queue::fake();
    config()->set('og-image-filament.disk', 'public');
    config()->set('og-image-filament.directory', 'og-images');

    $post = generatorPost('Synchronous title');
    registerGeneratorSource();
    app()->instance(
        OgImageRenderer::class,
        new OgImageRenderer(fn (string $html): Browsershot => new GeneratorBrowsershot($html)),
    );

    app(LivewireManager::class)->actingAs(generatorUser())
        ->test(OgImageGenerator::class)
        ->fillForm([
            'source' => PostResource::class,
            'entry' => $post->id,
        ])
        ->call('regenerate')
        ->assertNotified('OG image regenerated');

    Queue::assertNothingPushed();
    Storage::disk('public')->assertExists("og-images/posts/{$post->id}.png");

    expect(Storage::disk('public')->get("og-images/posts/{$post->id}.png"))
        ->toContain('Synchronous title');
});

function generatorUser(): User
{
    return User::query()->create([
        'name' => 'Admin',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
    ]);
}

function generatorPost(string $title): Post
{
    return Post::query()->create([
        'title' => $title,
        'slug' => str($title)->slug()->toString(),
        'summary' => fake()->sentence(),
        'is_visible' => true,
    ]);
}

function registerGeneratorSource(): void
{
    view()->addNamespace('test-og-images', dirname(__DIR__).'/Fixtures/views');
    $plugin = Filament::getDefaultPanel()->getPlugin('og-image-filament');

    if (! $plugin instanceof OgImageFilamentPlugin) {
        throw new LogicException('The test panel registered an unexpected OG image plugin.');
    }

    $plugin->getSources()[PostResource::class]
        ->template('test-og-images::source-card')
        ->dataUsing(fn (Post $post): PostOgImageData => PostOgImageData::from($post))
        ->pathUsing(fn (Post $post): string => "posts/{$post->id}.png");
}

final class GeneratorBrowsershot extends Browsershot
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
