<?php

declare(strict_types=1);

namespace HardImpact\OgImageFilament\Exceptions;

use OutOfBoundsException;

final class UnknownProperty extends OutOfBoundsException
{
    public static function named(string $key): self
    {
        return new self("OG image property [{$key}] is not configured.");
    }
}
