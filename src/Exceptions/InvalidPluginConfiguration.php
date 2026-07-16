<?php

declare(strict_types=1);

namespace HardImpact\OgImageFilament\Exceptions;

use InvalidArgumentException;

final class InvalidPluginConfiguration extends InvalidArgumentException
{
    public static function missingProperties(): self
    {
        return new self('The OG image Filament plugin must configure at least one property.');
    }

    public static function invalidProperty(mixed $property): self
    {
        return new self(sprintf(
            'OG image Filament plugin properties must extend Property; [%s] was given.',
            get_debug_type($property),
        ));
    }

    public static function duplicateProperty(string $key): self
    {
        return new self("OG image property [{$key}] is configured more than once.");
    }

    public static function missingSources(): self
    {
        return new self('The OG image Filament plugin must configure at least one resource source.');
    }

    public static function invalidSource(mixed $source): self
    {
        return new self(sprintf(
            'OG image Filament plugin sources must be ResourceSource instances; [%s] was given.',
            get_debug_type($source),
        ));
    }

    public static function duplicateSource(string $key): self
    {
        return new self("OG image source [{$key}] is configured more than once.");
    }

    public static function emptyTemplate(): self
    {
        return new self('The OG image Filament plugin template cannot be empty.');
    }

    public static function emptySlug(): self
    {
        return new self('The OG image Filament plugin slug cannot be empty.');
    }
}
