<?php

declare(strict_types=1);

use Filament\Panel;
use HardImpact\OgImageFilament\Exceptions\InvalidPluginConfiguration;
use HardImpact\OgImageFilament\Exceptions\InvalidSourceConfiguration;
use HardImpact\OgImageFilament\Filament\Pages\OgImageGenerator;
use HardImpact\OgImageFilament\OgImageFilamentPlugin;
use HardImpact\OgImageFilament\Sources\ResourceSource;
use Workbench\App\Data\PostOgImageData;
use Workbench\App\Filament\Resources\PostResource;
use Workbench\App\Models\Post;

it('registers its page and code-configured sources on a panel', function (): void {
    $source = configuredPluginSource();
    $plugin = OgImageFilamentPlugin::make()->sources([$source]);

    $panel = Panel::make()
        ->id('plugin-test')
        ->plugin($plugin);

    expect($panel->getPages())->toContain(OgImageGenerator::class)
        ->and($panel->getPlugin('og-image-filament'))->toBe($plugin)
        ->and($plugin->getSources())->toBe([PostResource::class => $source]);
});

it('exposes configurable navigation and route metadata', function (): void {
    $plugin = OgImageFilamentPlugin::make()
        ->sources([configuredPluginSource()])
        ->navigationLabel('Social images')
        ->navigationGroup('Content')
        ->navigationIcon('heroicon-o-photo')
        ->navigationSort(25)
        ->slug('social-images');

    expect($plugin->getNavigationLabel())->toBe('Social images')
        ->and($plugin->getNavigationGroup())->toBe('Content')
        ->and($plugin->getNavigationIcon())->toBe('heroicon-o-photo')
        ->and($plugin->getNavigationSort())->toBe(25)
        ->and($plugin->getSlug())->toBe('social-images');
});

it('rejects empty, duplicate, and incomplete source definitions', function (): void {
    $source = configuredPluginSource();

    expect(fn () => Panel::make()->id('empty-plugin-test')->plugin(
        OgImageFilamentPlugin::make(),
    ))->toThrow(InvalidPluginConfiguration::class)
        ->and(fn () => Panel::make()->id('duplicate-sources-test')->plugin(
            OgImageFilamentPlugin::make()->sources([$source, $source]),
        ))->toThrow(InvalidPluginConfiguration::class)
        ->and(fn () => Panel::make()->id('incomplete-source-test')->plugin(
            OgImageFilamentPlugin::make()->sources([
                ResourceSource::make(PostResource::class),
            ]),
        ))->toThrow(InvalidSourceConfiguration::class);
});

function configuredPluginSource(): ResourceSource
{
    return ResourceSource::make(PostResource::class)
        ->template('og-image-filament::card')
        ->dataUsing(fn (Post $post): PostOgImageData => PostOgImageData::from($post))
        ->pathUsing(fn (Post $post): string => "posts/{$post->id}.png");
}
