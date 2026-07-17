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
                    ->sources([
                        ResourceSource::make(PostResource::class)
                            ->template('og-image-filament::card')
                            ->dataUsing(fn (Post $post): PostOgImageData => PostOgImageData::from($post))
                            ->pathUsing(fn (Post $post): string => "posts/{$post->id}.png"),
                    ]),
            );
    }
}
