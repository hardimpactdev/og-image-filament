<?php

declare(strict_types=1);

use Filament\Panel;
use HardImpact\OgImageFilament\Exceptions\InvalidPluginConfiguration;
use HardImpact\OgImageFilament\Filament\Pages\OgImageGenerator;
use HardImpact\OgImageFilament\OgImageFilamentPlugin;
use HardImpact\OgImageFilament\Properties\TextProperty;
use HardImpact\OgImageFilament\Sources\ResourceSource;
use Workbench\App\Filament\Resources\PostResource;
use Workbench\App\Models\Post;

it('registers its page, template, sources, and properties on a panel', function (): void {
    $source = ResourceSource::make(PostResource::class)
        ->map(fn (Post $post): array => ['title' => $post->title]);

    $plugin = OgImageFilamentPlugin::make()
        ->template('og-image-filament::card')
        ->properties([TextProperty::make('title')->required()])
        ->sources([$source]);

    $panel = Panel::make()
        ->id('plugin-test')
        ->plugin($plugin);

    expect($panel->getPages())->toContain(OgImageGenerator::class)
        ->and($panel->getPlugin('og-image-filament'))->toBe($plugin)
        ->and($plugin->getTemplate())->toBe('og-image-filament::card')
        ->and($plugin->getProperties())->toHaveKey('title')
        ->and($plugin->getSources())->toBe([PostResource::class => $source]);
});

it('exposes configurable navigation and route metadata', function (): void {
    $plugin = OgImageFilamentPlugin::make()
        ->properties([TextProperty::make('title')])
        ->sources([
            ResourceSource::make(PostResource::class)
                ->map(fn (Post $post): array => ['title' => $post->title]),
        ])
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

it('rejects empty and duplicate plugin definitions', function (): void {
    $source = ResourceSource::make(PostResource::class)
        ->map(fn (Post $post): array => ['title' => $post->title]);

    expect(fn () => Panel::make()->id('empty-plugin-test')->plugin(
        OgImageFilamentPlugin::make(),
    ))->toThrow(InvalidPluginConfiguration::class)
        ->and(fn () => Panel::make()->id('duplicate-properties-test')->plugin(
            OgImageFilamentPlugin::make()
                ->properties([TextProperty::make('title'), TextProperty::make('title')])
                ->sources([$source]),
        ))->toThrow(InvalidPluginConfiguration::class)
        ->and(fn () => Panel::make()->id('duplicate-sources-test')->plugin(
            OgImageFilamentPlugin::make()
                ->properties([TextProperty::make('title')])
                ->sources([$source, $source]),
        ))->toThrow(InvalidPluginConfiguration::class);
});
