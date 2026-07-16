<?php

declare(strict_types=1);

namespace HardImpact\OgImageFilament\Exceptions;

use InvalidArgumentException;

final class InvalidMappedProperties extends InvalidArgumentException
{
    public static function duplicateDefinition(string $key): self
    {
        return new self("OG image property [{$key}] is configured more than once.");
    }

    public static function invalidDefinition(mixed $definition): self
    {
        return new self(sprintf(
            'OG image properties must extend Property; [%s] was given.',
            get_debug_type($definition),
        ));
    }

    public static function unknownMappingKey(string $key): self
    {
        return new self("The mapped OG image property [{$key}] is not configured.");
    }

    public static function unsupportedValue(string $key, mixed $value): self
    {
        return new self(sprintf(
            'The mapped OG image property [%s] must be a string, Stringable, or null; [%s] was given.',
            $key,
            get_debug_type($value),
        ));
    }

    /**
     * @param  array<int, string>  $messages
     */
    public static function validationFailed(array $messages): self
    {
        return new self('The mapped OG image properties are invalid: '.implode(' ', $messages));
    }
}
