<?php

declare(strict_types=1);

namespace HardImpact\OgImageFilament\Settings;

use HardImpact\OgImageFilament\Properties\Property;
use HardImpact\OgImageFilament\Sources\ResourceSource;
use Illuminate\Database\Eloquent\Model;
use Stringable;

final readonly class OgImageConfiguration
{
    /**
     * @param  array<string, Property>  $properties
     * @param  array<string, array<string, array{source: string, value: string}>>  $mappings
     */
    public function __construct(
        public array $properties,
        public array $mappings,
    ) {}

    /**
     * @return array<string, ?string>
     */
    public function mapRecord(ResourceSource $source, Model $record): array
    {
        $resourceMappings = $this->mappings[$source->getKey()] ?? [];

        if ($resourceMappings === [] && $source->hasMapper()) {
            $mappedRecord = $source->mapRecord($record);
            $values = [];

            foreach ($this->properties as $key => $property) {
                $values[$property->key] = self::normalizeValue(
                    $mappedRecord[$key] ?? null,
                );
            }

            return $values;
        }

        $values = [];

        foreach ($this->properties as $key => $property) {
            $mapping = $resourceMappings[$key] ?? null;

            if ($mapping === null) {
                $values[$property->key] = null;

                continue;
            }

            $sourceType = MappingSource::tryFrom($mapping['source']);

            $value = match ($sourceType) {
                MappingSource::Column => $record->getAttribute($mapping['value']),
                MappingSource::ModelValue => $source->resolveModelValue($mapping['value'], $record),
                MappingSource::StaticText => $mapping['value'],
                null => null,
            };

            $values[$property->key] = self::normalizeValue($value);
        }

        return $values;
    }

    private static function normalizeValue(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_string($value) => $value,
            is_int($value), is_float($value) => (string) $value,
            is_bool($value) => $value ? '1' : '0',
            $value instanceof Stringable => (string) $value,
            default => null,
        };
    }
}
