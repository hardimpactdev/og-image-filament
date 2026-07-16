<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use HardImpact\OgImageFilament\Filament\Pages\OgImageGenerator;
use HardImpact\OgImageFilament\Models\OgImageSetting;
use HardImpact\OgImageFilament\OgImageFilamentPlugin;
use HardImpact\OgImageFilament\Settings\ConfigurationRepository;
use HardImpact\OgImageFilament\Settings\SettingsForm;
use Livewire\LivewireManager;
use Workbench\App\Filament\Resources\PostResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

use function Pest\Laravel\get;

it('requires panel authentication', function (): void {
    get('/admin/og-image-generator')
        ->assertRedirect('/admin/login');
});

it('renders the configured template when Livewire has no current panel', function (): void {
    Filament::setCurrentPanel(null);

    app(LivewireManager::class)->actingAs(User::query()->create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'password',
    ]))
        ->test(OgImageGenerator::class)
        ->assertSee('data-og-card', escape: false);
});

it('selects a resource record and populates mapped properties', function (): void {
    $post = Post::query()->create([
        'title' => 'Mapped title',
        'slug' => 'mapped-title',
        'summary' => 'Mapped summary',
        'is_visible' => true,
    ]);

    app(LivewireManager::class)->actingAs(User::query()->create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'password',
    ]))
        ->test(OgImageGenerator::class)
        ->fillForm([
            'source' => PostResource::class,
            'entry' => $post->id,
        ])
        ->assertSet('data.properties.title', 'Mapped title')
        ->assertSet('data.properties.description', 'Mapped summary');
});

it('replaces overrides when another entry is selected', function (): void {
    $first = Post::query()->create([
        'title' => 'First mapped title',
        'slug' => 'first-mapped-title',
        'summary' => 'First summary',
        'is_visible' => true,
    ]);
    $second = Post::query()->create([
        'title' => 'Second mapped title',
        'slug' => 'second-mapped-title',
        'summary' => 'Second summary',
        'is_visible' => true,
    ]);
    $user = User::query()->create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    app(LivewireManager::class)->actingAs($user)
        ->test(OgImageGenerator::class)
        ->fillForm([
            'source' => PostResource::class,
            'entry' => $first->id,
        ])
        ->set('data.properties.title', 'Temporary override')
        ->set('data.entry', $second->id)
        ->assertSet('data.properties.title', 'Second mapped title');
});

it('keeps mapper failures operator safe', function (): void {
    $post = Post::query()->create([
        'title' => 'Broken mapping',
        'slug' => 'broken-mapping',
        'summary' => 'Summary',
        'is_visible' => true,
    ]);
    $user = User::query()->create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $plugin = Filament::getDefaultPanel()->getPlugin('og-image-filament');

    if (! $plugin instanceof OgImageFilamentPlugin) {
        throw new LogicException('The test panel registered an unexpected OG image plugin.');
    }

    $plugin->getSources()[PostResource::class]
        ->defaultMappings([])
        ->map(fn (): never => throw new RuntimeException('Sensitive mapper failure'));

    app(LivewireManager::class)->actingAs($user)
        ->test(OgImageGenerator::class)
        ->fillForm([
            'source' => PostResource::class,
            'entry' => $post->id,
        ])
        ->assertNotified('The entry could not be loaded');
});

it('validates the configured dynamic properties before generation', function (): void {
    $post = Post::query()->create([
        'title' => 'Mapped title',
        'slug' => 'mapped-title',
        'summary' => 'Mapped summary',
        'is_visible' => true,
    ]);
    $user = User::query()->create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    app(LivewireManager::class)->actingAs($user)
        ->test(OgImageGenerator::class)
        ->fillForm([
            'source' => PostResource::class,
            'entry' => $post->id,
        ])
        ->set('data.properties.title', '')
        ->call('generate')
        ->assertHasFormErrors(['properties.title' => 'required']);
});

it('shows PHP defaults in a same-page configuration tab', function (): void {
    $user = User::query()->create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    app(LivewireManager::class)->actingAs($user)
        ->test(OgImageGenerator::class)
        ->assertSee('Generate')
        ->assertSee('Configure')
        ->assertSet('settingsData', function (array $settings): bool {
            $properties = array_values($settings['properties']);
            $resourceKey = SettingsForm::resourceStateKey(PostResource::class);

            return $properties[0]['key'] === 'label'
                && $properties[1]['key'] === 'title'
                && preg_match('/^[a-z0-9_]+$/', $resourceKey) === 1
                && $settings['mappings'][$resourceKey]['title']['source'] === 'column'
                && $settings['mappings'][$resourceKey]['title']['column'] === 'title';
        });
});

it('saves visual property and resource mapping configuration', function (): void {
    $user = User::query()->create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    app(LivewireManager::class)->actingAs($user)
        ->test(OgImageGenerator::class)
        ->fillForm([
            'properties' => [
                [
                    'key' => 'headline',
                    'label' => 'Headline',
                    'type' => 'text',
                    'required' => true,
                    'max_length' => 100,
                ],
                [
                    'key' => 'eyebrow',
                    'label' => 'Eyebrow',
                    'type' => 'text',
                    'required' => false,
                    'max_length' => 40,
                ],
            ],
            'mappings' => [
                SettingsForm::resourceStateKey(PostResource::class) => [
                    'headline' => [
                        'source' => 'column',
                        'column' => 'title',
                        'static' => null,
                    ],
                    'eyebrow' => [
                        'source' => 'static',
                        'column' => null,
                        'static' => 'Article',
                    ],
                ],
            ],
        ], 'settingsForm')
        ->call('saveSettings')
        ->assertNotified('OG image configuration saved');

    $setting = OgImageSetting::query()->where('panel_id', 'admin')->firstOrFail();

    expect($setting->properties)->toBe([
        [
            'key' => 'headline',
            'label' => 'Headline',
            'type' => 'text',
            'required' => true,
            'max_length' => 100,
        ],
        [
            'key' => 'eyebrow',
            'label' => 'Eyebrow',
            'type' => 'text',
            'required' => false,
            'max_length' => 40,
        ],
    ])->and($setting->mappings)->toBe([
        PostResource::class => [
            'headline' => ['source' => 'column', 'value' => 'title'],
            'eyebrow' => ['source' => 'static', 'value' => 'Article'],
        ],
    ]);
});

it('uses saved properties and mappings in the generator', function (): void {
    $post = Post::query()->create([
        'title' => 'Database configured title',
        'slug' => 'database-configured-title',
        'summary' => 'Summary',
        'is_visible' => true,
    ]);
    $user = User::query()->create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);
    $plugin = Filament::getDefaultPanel()->getPlugin('og-image-filament');

    if (! $plugin instanceof OgImageFilamentPlugin) {
        throw new LogicException('The test panel registered an unexpected OG image plugin.');
    }

    resolve(ConfigurationRepository::class)->save(
        panelId: 'admin',
        propertyDefinitions: [
            [
                'key' => 'headline',
                'label' => 'Headline',
                'type' => 'text',
                'required' => true,
                'max_length' => null,
            ],
            [
                'key' => 'note',
                'label' => 'Note',
                'type' => 'textarea',
                'required' => false,
                'max_length' => null,
            ],
        ],
        mappings: [
            PostResource::class => [
                'headline' => ['source' => 'column', 'value' => 'title'],
            ],
        ],
        plugin: $plugin,
    );

    app(LivewireManager::class)->actingAs($user)
        ->test(OgImageGenerator::class)
        ->fillForm([
            'source' => PostResource::class,
            'entry' => $post->id,
        ])
        ->assertSet('data.properties.headline', 'Database configured title')
        ->assertSet('data.properties.note', null);
});
