<?php

declare(strict_types=1);

namespace HardImpact\OgImageFilament\Tests;

use Filament\Actions\ActionsServiceProvider;
use Filament\Facades\Filament;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use HardImpact\OgImageFilament\OgImageFilamentServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Livewire\LivewireServiceProvider;
use Livewire\Mechanisms\DataStore;
use Orchestra\Testbench\TestCase as Orchestra;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;
use Workbench\App\Policies\PostPolicy;
use Workbench\App\Providers\Filament\AdminPanelProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $application = $this->app;

        if ($application === null) {
            throw new \LogicException('The Testbench application was not created.');
        }

        $application->singleton(DataStore::class);

        Gate::policy(Post::class, PostPolicy::class);
        Filament::setCurrentPanel(Filament::getDefaultPanel());
        View::share('errors', new ViewErrorBag);
    }

    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return [
            SupportServiceProvider::class,
            ActionsServiceProvider::class,
            SchemasServiceProvider::class,
            FormsServiceProvider::class,
            NotificationsServiceProvider::class,
            FilamentServiceProvider::class,
            OgImageFilamentServiceProvider::class,
            AdminPanelProvider::class,
        ];
    }

    /** @return array<int, class-string> */
    protected function getApplicationProviders($app): array
    {
        return [
            ...parent::getApplicationProviders($app),
            LivewireServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('auth.providers.users.model', User::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../workbench/database/migrations');
    }
}
