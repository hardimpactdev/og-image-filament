<?php

declare(strict_types=1);

use HardImpact\OgImageFilament\Storage\GeneratedOgImages;
use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
    config()->set('og-image-filament.disk', 'public');
    config()->set('og-image-filament.directory', 'og-images');
});

it('atomically replaces generated images and resolves existing urls', function (): void {
    $images = resolve(GeneratedOgImages::class);

    expect($images->url('articles/1.png'))->toBeNull();

    $images->replace('articles/1.png', 'first-png');

    Storage::disk('public')->assertExists('og-images/articles/1.png');
    expect(Storage::disk('public')->get('og-images/articles/1.png'))->toBe('first-png')
        ->and($images->url('articles/1.png'))->toEndWith('/storage/og-images/articles/1.png');

    $images->replace('articles/1.png', 'second-png');

    expect(Storage::disk('public')->get('og-images/articles/1.png'))->toBe('second-png')
        ->and(Storage::disk('public')->allFiles('og-images'))->toBe([
            'og-images/articles/1.png',
        ]);
});

it('preserves the last good image when a replacement write fails', function (): void {
    $workingImages = resolve(GeneratedOgImages::class);
    $workingImages->replace('articles/1.png', 'last-good-png');

    $failingImages = new GeneratedOgImages(
        app(FilesystemManager::class),
        function (Cloud $disk, string $path, string $png): bool {
            throw new RuntimeException('Disk write failed');
        },
    );

    expect(fn () => $failingImages->replace('articles/1.png', 'broken-png'))
        ->toThrow(RuntimeException::class, 'Disk write failed')
        ->and(Storage::disk('public')->get('og-images/articles/1.png'))->toBe('last-good-png')
        ->and(Storage::disk('public')->allFiles('og-images'))->toBe([
            'og-images/articles/1.png',
        ]);
});
