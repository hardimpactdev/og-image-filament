<?php

declare(strict_types=1);

namespace HardImpact\OgImageFilament\Filament\Pages;

use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Panel;
use HardImpact\OgImageFilament\OgImageFilamentPlugin;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

final class OgImageGenerator extends Page
{
    protected string $view = 'og-image-filament::filament.pages.generator';

    public static function getSlug(?Panel $panel = null): string
    {
        return self::getPlugin($panel)->getSlug();
    }

    public static function getNavigationLabel(): string
    {
        return self::getPlugin()->getNavigationLabel();
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return self::getPlugin()->getNavigationGroup();
    }

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return self::getPlugin()->getNavigationIcon();
    }

    public static function getNavigationSort(): ?int
    {
        return self::getPlugin()->getNavigationSort();
    }

    public function getTitle(): string
    {
        return self::getPlugin()->getNavigationLabel();
    }

    private static function getPlugin(?Panel $panel = null): OgImageFilamentPlugin
    {
        $panel ??= Filament::getCurrentPanel() ?? Filament::getDefaultPanel();
        $plugin = $panel->getPlugin('og-image-filament');

        if (! $plugin instanceof OgImageFilamentPlugin) {
            throw new \LogicException('The registered og-image-filament plugin has an unexpected type.');
        }

        return $plugin;
    }
}
