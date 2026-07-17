<?php

declare(strict_types=1);

namespace HardImpact\OgImageFilament\Exceptions;

use InvalidArgumentException;

final class InvalidSettings extends InvalidArgumentException
{
    public static function invalidStoredConfiguration(string $panelId): self
    {
        return new self("The stored OG image configuration for Filament panel [{$panelId}] is invalid.");
    }

    public static function invalidMapping(string $resource, string $property): self
    {
        return new self("The OG image mapping for property [{$property}] on resource [{$resource}] is invalid.");
    }

    public static function unknownColumn(string $resource, string $property, string $column): self
    {
        return new self("The OG image mapping for property [{$property}] on resource [{$resource}] references unknown column [{$column}].");
    }

    public static function unknownModelValue(string $resource, string $property, string $value): self
    {
        return new self("The OG image mapping for property [{$property}] on resource [{$resource}] references unknown model value [{$value}].");
    }
}
