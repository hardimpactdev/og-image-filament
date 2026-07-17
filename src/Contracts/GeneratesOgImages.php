<?php

declare(strict_types=1);

namespace HardImpact\OgImageFilament\Contracts;

use Filament\Resources\Resource;

interface GeneratesOgImages
{
    public function ogImagePanelId(): string;

    /** @return class-string<resource> */
    public function ogImageSource(): string;
}
