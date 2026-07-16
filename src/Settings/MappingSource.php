<?php

declare(strict_types=1);

namespace HardImpact\OgImageFilament\Settings;

enum MappingSource: string
{
    case Column = 'column';
    case StaticText = 'static';
}
