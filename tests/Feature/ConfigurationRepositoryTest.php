<?php

declare(strict_types=1);

use HardImpact\OgImageFilament\Exceptions\InvalidSettings;
use HardImpact\OgImageFilament\Models\OgImageSetting;
use HardImpact\OgImageFilament\OgImageFilamentPlugin;
use HardImpact\OgImageFilament\Properties\TextProperty;
use HardImpact\OgImageFilament\Settings\ConfigurationRepository;
use HardImpact\OgImageFilament\Sources\ResourceSource;
use Workbench\App\Filament\Resources\PostResource;
use Workbench\App\Models\Post;

function configurationPlugin(): OgImageFilamentPlugin
{
    return OgImageFilamentPlugin::make()
        ->properties([
            TextProperty::make('label')->required(),
            TextProperty::make('title')->required(),
        ])
        ->sources([
            ResourceSource::make(PostResource::class)
                ->defaultMappings([
                    'label' => ['source' => 'static', 'value' => 'Post'],
                    'title' => ['source' => 'column', 'value' => 'title'],
                ]),
        ]);
}

it('uses PHP defaults until panel settings are saved', function (): void {
    $configuration = resolve(ConfigurationRepository::class)
        ->forPanel('admin', configurationPlugin());

    expect(OgImageSetting::query()->count())->toBe(0)
        ->and(array_keys($configuration->properties))->toBe(['label', 'title'])
        ->and($configuration->mappings)->toBe([
            PostResource::class => [
                'label' => ['source' => 'static', 'value' => 'Post'],
                'title' => ['source' => 'column', 'value' => 'title'],
            ],
        ]);
});

it('uses saved panel settings instead of PHP defaults', function (): void {
    $repository = resolve(ConfigurationRepository::class);
    $repository->save(
        panelId: 'admin',
        propertyDefinitions: [[
            'key' => 'headline',
            'label' => 'Headline',
            'type' => 'text',
            'required' => true,
            'max_length' => 120,
        ]],
        mappings: [
            PostResource::class => [
                'headline' => ['source' => 'column', 'value' => 'summary'],
            ],
        ],
        plugin: configurationPlugin(),
    );

    $configuration = $repository->forPanel('admin', configurationPlugin());

    expect(array_keys($configuration->properties))->toBe(['headline'])
        ->and($configuration->mappings)->toBe([
            PostResource::class => [
                'headline' => ['source' => 'column', 'value' => 'summary'],
            ],
        ]);
});

it('maps column and static values without fallbacks', function (): void {
    $post = Post::query()->create([
        'title' => 'Mapped title',
        'slug' => 'mapped-title',
        'summary' => 'Mapped summary',
        'is_visible' => true,
    ]);

    $configuration = resolve(ConfigurationRepository::class)->save(
        panelId: 'admin',
        propertyDefinitions: [
            [
                'key' => 'label',
                'label' => 'Label',
                'type' => 'text',
                'required' => false,
                'max_length' => null,
            ],
            [
                'key' => 'identifier',
                'label' => 'Identifier',
                'type' => 'text',
                'required' => false,
                'max_length' => null,
            ],
            [
                'key' => 'description',
                'label' => 'Description',
                'type' => 'text',
                'required' => false,
                'max_length' => null,
            ],
        ],
        mappings: [
            PostResource::class => [
                'label' => ['source' => 'static', 'value' => 'Article'],
                'identifier' => ['source' => 'column', 'value' => 'id'],
                'description' => ['source' => 'column', 'value' => 'summary'],
            ],
        ],
        plugin: configurationPlugin(),
    );

    $source = configurationPlugin()->getSources()[PostResource::class];

    expect($configuration->mapRecord($source, $post))->toBe([
        'label' => 'Article',
        'identifier' => (string) $post->id,
        'description' => 'Mapped summary',
    ]);

    $post->setAttribute('summary', null);

    expect($configuration->mapRecord($source, $post)['description'])->toBeNull();

    $post->setAttribute('summary', ['unsupported']);

    expect($configuration->mapRecord($source, $post)['description'])->toBeNull();
});

it('removes stale mappings and rejects unknown model columns', function (): void {
    $repository = resolve(ConfigurationRepository::class);
    $configuration = $repository->save(
        panelId: 'admin',
        propertyDefinitions: [[
            'key' => 'title',
            'label' => 'Title',
            'type' => 'text',
            'required' => false,
            'max_length' => null,
        ]],
        mappings: [
            'App\\Filament\\Resources\\MissingResource' => [
                'title' => ['source' => 'static', 'value' => 'Ignored'],
            ],
            PostResource::class => [
                'title' => ['source' => 'column', 'value' => 'title'],
                'removed_property' => ['source' => 'static', 'value' => 'Ignored'],
            ],
        ],
        plugin: configurationPlugin(),
    );

    expect($configuration->mappings)->toBe([
        PostResource::class => [
            'title' => ['source' => 'column', 'value' => 'title'],
        ],
    ]);

    expect(fn () => $repository->save(
        panelId: 'admin',
        propertyDefinitions: [[
            'key' => 'title',
            'label' => 'Title',
            'type' => 'text',
            'required' => false,
            'max_length' => null,
        ]],
        mappings: [
            PostResource::class => [
                'title' => ['source' => 'column', 'value' => 'missing_column'],
            ],
        ],
        plugin: configurationPlugin(),
    ))->toThrow(InvalidSettings::class);
});
