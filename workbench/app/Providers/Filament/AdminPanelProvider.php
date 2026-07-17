<?php

declare(strict_types=1);

namespace Workbench\App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use HardImpact\OgImageFilament\OgImageFilamentPlugin;
use HardImpact\OgImageFilament\Properties\TextareaProperty;
use HardImpact\OgImageFilament\Properties\TextProperty;
use HardImpact\OgImageFilament\Properties\UrlProperty;
use HardImpact\OgImageFilament\Sources\ModelValue;
use HardImpact\OgImageFilament\Sources\ResourceSource;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Workbench\App\Data\PostOgImageData;
use Workbench\App\Filament\Resources\PostResource;
use Workbench\App\Models\Post;

final class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugin(
                OgImageFilamentPlugin::make()
                    ->properties([
                        TextProperty::make('label')->required()->maxLength(40),
                        TextProperty::make('title')->required()->maxLength(180),
                        TextareaProperty::make('description')->maxLength(240),
                        UrlProperty::make('url')->required(),
                    ])
                    ->sources([
                        ResourceSource::make(PostResource::class)
                            ->template('og-image-filament::card')
                            ->dataUsing(fn (Post $post): PostOgImageData => PostOgImageData::from($post))
                            ->pathUsing(fn (Post $post): string => "posts/{$post->id}.png")
                            ->modelValues([
                                ModelValue::make('public_url')
                                    ->label('Public URL')
                                    ->resolveUsing(fn (Post $post): string => url("/posts/{$post->slug}")),
                            ])
                            ->defaultMappings([
                                'label' => ['source' => 'static', 'value' => 'Post'],
                                'title' => ['source' => 'column', 'value' => 'title'],
                                'description' => ['source' => 'column', 'value' => 'summary'],
                                'url' => ['source' => 'model_value', 'value' => 'public_url'],
                            ])
                            ->map(fn (Post $post): array => [
                                'label' => 'Post',
                                'title' => $post->title,
                                'description' => $post->summary,
                                'url' => url("/posts/{$post->slug}"),
                            ]),
                    ]),
            );
    }
}
