<?php

declare(strict_types=1);

namespace HardImpact\OgImageFilament\Exceptions;

use InvalidArgumentException;

final class InvalidSourceConfiguration extends InvalidArgumentException
{
    public static function invalidResource(string $resource): self
    {
        return new self("OG image source [{$resource}] must extend Filament\\Resources\\Resource.");
    }

    public static function invalidSearchColumns(string $resource): self
    {
        return new self("OG image source [{$resource}] must configure at least one non-empty search column.");
    }

    public static function missingSearchColumns(string $resource): self
    {
        return new self("OG image source [{$resource}] has no globally searchable or record-title attributes; configure search columns explicitly.");
    }

    public static function missingMapper(string $resource): self
    {
        return new self("OG image source [{$resource}] must configure a record mapper before mapping entries.");
    }

    public static function invalidRecord(string $resource, mixed $record): self
    {
        return new self(sprintf(
            'OG image source [%s] cannot map record [%s].',
            $resource,
            get_debug_type($record),
        ));
    }

    public static function invalidMappedResult(string $resource): self
    {
        return new self("The mapper for OG image source [{$resource}] must return an array with string keys.");
    }

    public static function invalidRecordTitle(string $resource, mixed $title): self
    {
        return new self(sprintf(
            'The record title for OG image source [%s] must be a string, Stringable, or Htmlable; [%s] was returned.',
            $resource,
            get_debug_type($title),
        ));
    }
}
