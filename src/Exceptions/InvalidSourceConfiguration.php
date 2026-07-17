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

    public static function invalidTemplate(string $resource): self
    {
        return new self("OG image source [{$resource}] must configure a non-empty Blade view.");
    }

    public static function missingTemplate(string $resource): self
    {
        return new self("OG image source [{$resource}] must configure a Blade view.");
    }

    public static function missingDataResolver(string $resource): self
    {
        return new self("OG image source [{$resource}] must configure a data resolver.");
    }

    public static function invalidData(string $resource, mixed $data): self
    {
        return new self(sprintf(
            'The data resolver for OG image source [%s] must return an object; [%s] was returned.',
            $resource,
            get_debug_type($data),
        ));
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

    public static function invalidMapping(string $resource, string $property): self
    {
        return new self("The default mapping for OG image property [{$property}] on source [{$resource}] is invalid.");
    }

    public static function invalidMappingColumn(string $resource, string $property, string $column): self
    {
        return new self("The default mapping for OG image property [{$property}] on source [{$resource}] references unknown column [{$column}].");
    }

    public static function invalidModelValueKey(string $key): self
    {
        return new self("OG image model value key [{$key}] must use lower snake case.");
    }

    public static function invalidModelValueLabel(string $key): self
    {
        return new self("OG image model value [{$key}] must have a non-empty label.");
    }

    public static function duplicateModelValue(string $resource, string $key): self
    {
        return new self("OG image source [{$resource}] registers model value [{$key}] more than once.");
    }

    public static function unknownModelValue(string $resource, string $key): self
    {
        return new self("OG image source [{$resource}] does not register model value [{$key}].");
    }

    public static function missingModelValueResolver(string $key): self
    {
        return new self("OG image model value [{$key}] must configure a resolver.");
    }

    public static function missingPathResolver(string $resource): self
    {
        return new self("OG image source [{$resource}] must configure a deterministic path resolver.");
    }

    public static function invalidPath(string $resource, mixed $path): self
    {
        return new self(sprintf(
            'The OG image path for source [%s] must be a non-empty relative path without traversal; [%s] was returned.',
            $resource,
            get_debug_type($path),
        ));
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
