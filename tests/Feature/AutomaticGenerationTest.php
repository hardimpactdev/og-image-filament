<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Resources\Resource;
use HardImpact\OgImageFilament\Concerns\InteractsWithOgImages;
use HardImpact\OgImageFilament\Contracts\GeneratesOgImages;
use HardImpact\OgImageFilament\OgImageFilamentPlugin;
use HardImpact\OgImageFilament\Rendering\OgImageRenderer;
use HardImpact\OgImageFilament\Sources\ResourceSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;

beforeEach(function (): void {
    Storage::fake('public');
    Queue::fake();
    config()->set('og-image-filament.disk', 'public');
    config()->set('og-image-filament.directory', 'og-images');
    view()->addNamespace('test-og-images', dirname(__DIR__).'/Fixtures/views');

    $plugin = Filament::getDefaultPanel()->getPlugin('og-image-filament');

    if (! $plugin instanceof OgImageFilamentPlugin) {
        throw new LogicException('The test panel registered an unexpected OG image plugin.');
    }

    $plugin->sources([
        ...array_values($plugin->getSources()),
        ResourceSource::make(AutomaticallyGeneratedPostResource::class)
            ->template('test-og-images::source-card')
            ->dataUsing(fn (AutomaticallyGeneratedPost $post): AutomaticPostData => new AutomaticPostData($post->title))
            ->pathUsing(fn (AutomaticallyGeneratedPost $post): string => "automatic-posts/{$post->id}.png"),
    ]);

    app()->instance(
        OgImageRenderer::class,
        new OgImageRenderer(fn (string $html): Browsershot => new AutomaticBrowsershot($html)),
    );
});

it('regenerates only after the surrounding transaction commits', function (): void {
    $post = automaticPost();
    $path = "og-images/automatic-posts/{$post->id}.png";

    DB::transaction(function () use ($post, $path): void {
        $post->update(['title' => 'Changed title']);

        Storage::disk('public')->assertMissing($path);
    });

    Queue::assertNothingPushed();
    Storage::disk('public')->assertExists($path);

    expect(Storage::disk('public')->get($path))->toContain('Changed title');
});

it('regenerates immediately when no transaction is open', function (): void {
    $post = automaticPost();

    $post->update(['title' => 'Changed title']);

    Queue::assertNothingPushed();
    Storage::disk('public')->assertExists("og-images/automatic-posts/{$post->id}.png");
});

it('propagates synchronous rendering failures', function (): void {
    $post = automaticPost();
    app()->instance(
        OgImageRenderer::class,
        new OgImageRenderer(fn (string $html): never => throw new RuntimeException('Chrome failed')),
    );

    expect(fn () => $post->update(['title' => 'Broken title']))
        ->toThrow(RuntimeException::class, 'Chrome failed');
});

it('deletes the generated PNG before deleting the model', function (): void {
    $post = automaticPost();
    $post->update(['title' => 'Generated title']);
    $path = "og-images/automatic-posts/{$post->id}.png";

    Storage::disk('public')->assertExists($path);

    $post->delete();

    Storage::disk('public')->assertMissing($path);
    expect(AutomaticallyGeneratedPost::query()->find($post->id))->toBeNull();
});

function automaticPost(): AutomaticallyGeneratedPost
{
    return AutomaticallyGeneratedPost::withoutEvents(
        fn (): AutomaticallyGeneratedPost => AutomaticallyGeneratedPost::query()->create([
            'title' => 'Original title',
            'slug' => fake()->unique()->slug(),
            'summary' => fake()->sentence(),
            'is_visible' => true,
        ]),
    );
}

final readonly class AutomaticPostData
{
    public function __construct(public string $title) {}
}

/** @extends resource<AutomaticallyGeneratedPost> */
final class AutomaticallyGeneratedPostResource extends Resource
{
    protected static ?string $model = AutomaticallyGeneratedPost::class;

    protected static ?string $recordTitleAttribute = 'title';
}

/**
 * @property int $id
 * @property string $title
 */
final class AutomaticallyGeneratedPost extends Model implements GeneratesOgImages
{
    use InteractsWithOgImages;

    protected $table = 'posts';

    protected $guarded = [];

    public function ogImagePanelId(): string
    {
        return 'admin';
    }

    public function ogImageSource(): string
    {
        return AutomaticallyGeneratedPostResource::class;
    }
}

final class AutomaticBrowsershot extends Browsershot
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
