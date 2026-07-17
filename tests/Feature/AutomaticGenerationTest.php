<?php

declare(strict_types=1);

use HardImpact\OgImageFilament\Concerns\InteractsWithOgImages;
use HardImpact\OgImageFilament\Contracts\GeneratesOgImages;
use HardImpact\OgImageFilament\Jobs\GenerateOgImage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Workbench\App\Filament\Resources\PostResource;

it('queues regeneration only after the surrounding transaction commits', function (): void {
    Queue::fake();
    $post = AutomaticallyGeneratedPost::withoutEvents(
        fn (): AutomaticallyGeneratedPost => AutomaticallyGeneratedPost::query()->create([
            'title' => 'Original title',
            'slug' => 'original-title',
            'summary' => 'Summary',
            'is_visible' => true,
        ]),
    );

    DB::transaction(function () use ($post): void {
        $post->update(['title' => 'Changed title']);

        Queue::assertNothingPushed();
    });

    Queue::assertPushed(
        GenerateOgImage::class,
        fn (GenerateOgImage $job): bool => $job->panelId === 'admin'
            && $job->source === PostResource::class
            && $job->record === $post->getRouteKey(),
    );
});

it('queues regeneration immediately when no transaction is open', function (): void {
    Queue::fake();
    $post = AutomaticallyGeneratedPost::withoutEvents(
        fn (): AutomaticallyGeneratedPost => AutomaticallyGeneratedPost::query()->create([
            'title' => 'Original title',
            'slug' => 'original-title',
            'summary' => 'Summary',
            'is_visible' => true,
        ]),
    );

    $post->update(['title' => 'Changed title']);

    Queue::assertPushed(GenerateOgImage::class, 1);
});

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
        return PostResource::class;
    }
}
