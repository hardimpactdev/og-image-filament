<?php

declare(strict_types=1);

namespace HardImpact\OgImageFilament\Exceptions;

use InvalidArgumentException;

final class InvalidPropertyConfiguration extends InvalidArgumentException
{
    public static function invalidKey(string $key): self
    {
        return new self("OG image property keys must use snake_case and start with a letter; [{$key}] is invalid.");
    }

    public static function invalidMaximumLength(string $key, ?int $length): self
    {
        $configuredLength = $length === null ? 'null' : (string) $length;

        return new self("The maximum length for OG image property [{$key}] must be greater than zero; [{$configuredLength}] was given.");
    }
}
