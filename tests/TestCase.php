<?php

declare(strict_types=1);

namespace HardImpact\OgImageFilament\Tests;

use Filament\Facades\Filament;
use Filament\FilamentServiceProvider;
use Filament\Panel;
use HardImpact\OgImageFilament\OgImageFilamentServiceProvider;
use Illuminate\Support\Facades\Gate;
use Orchestra\Testbench\TestCase as Orchestra;
use Workbench\App\Models\Post;
use Workbench\App\Policies\PostPolicy;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Gate::policy(Post::class, PostPolicy::class);

        $panel = Panel::make()
            ->id('admin')
            ->default();

        Filament::registerPanel($panel);
        Filament::setCurrentPanel($panel);
    }

    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return [
            FilamentServiceProvider::class,
            OgImageFilamentServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../workbench/database/migrations');
    }
}
