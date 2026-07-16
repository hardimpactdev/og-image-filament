<?php

declare(strict_types=1);

namespace HardImpact\OgImageFilament;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class OgImageFilamentServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('og-image-filament')
            ->hasViews();
    }
}
