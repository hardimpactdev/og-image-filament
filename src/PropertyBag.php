<?php

declare(strict_types=1);

namespace HardImpact\OgImageFilament;

use HardImpact\OgImageFilament\Exceptions\InvalidMappedProperties;
use HardImpact\OgImageFilament\Exceptions\UnknownProperty;
use HardImpact\OgImageFilament\Properties\Property;
use Illuminate\Support\Facades\Validator;
use Stringable;

final readonly class PropertyBag
{
    /**
     * @param  array<string, Property>  $properties
     * @param  array<string, ?string>  $values
     */
    private function __construct(
        private array $properties,
        private array $values,
    ) {}

    /**
     * @param  array<int, mixed>  $properties
     * @param  array<array-key, mixed>  $values
     */
    public static function fromMapping(array $properties, array $values): self
    {
        $propertiesByKey = self::keyProperties($properties);

        foreach ($values as $key => $value) {
            if (! is_string($key) || ! array_key_exists($key, $propertiesByKey)) {
                throw InvalidMappedProperties::unknownMappingKey((string) $key);
            }
        }

        $normalizedValues = [];
        $rules = [];

        foreach ($propertiesByKey as $key => $property) {
            $normalizedValues[$key] = self::normalizeMappedValue($key, $values[$key] ?? null);
            $rules[$key] = $property->rules();
        }

        $validator = Validator::make($normalizedValues, $rules);

        if ($validator->fails()) {
            throw InvalidMappedProperties::validationFailed($validator->errors()->all());
        }

        return new self($propertiesByKey, $normalizedValues);
    }

    /**
     * @param  array<int, mixed>  $properties
     * @param  array<array-key, mixed>  $values
     */
    public static function fromState(array $properties, array $values): self
    {
        $propertiesByKey = self::keyProperties($properties);
        $normalizedValues = [];

        foreach ($propertiesByKey as $key => $property) {
            $value = $values[$key] ?? null;

            $normalizedValues[$key] = match (true) {
                is_string($value) => $value,
                $value instanceof Stringable => (string) $value,
                default => null,
            };
        }

        return new self($propertiesByKey, $normalizedValues);
    }

    public function get(string $key): ?string
    {
        if (! $this->has($key)) {
            throw UnknownProperty::named($key);
        }

        return $this->values[$key];
    }

    public function string(string $key): string
    {
        return $this->get($key) ?? '';
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->properties);
    }

    public function filled(string $key): bool
    {
        $value = $this->get($key);

        return $value !== null && trim($value) !== '';
    }

    /**
     * @return array<string, ?string>
     */
    public function all(): array
    {
        return $this->values;
    }

    /**
     * @param  array<int, mixed>  $properties
     * @return array<string, Property>
     */
    private static function keyProperties(array $properties): array
    {
        $propertiesByKey = [];

        foreach ($properties as $property) {
            if (! $property instanceof Property) {
                throw InvalidMappedProperties::invalidDefinition($property);
            }

            if (array_key_exists($property->key, $propertiesByKey)) {
                throw InvalidMappedProperties::duplicateDefinition($property->key);
            }

            $propertiesByKey[$property->key] = $property;
        }

        return $propertiesByKey;
    }

    private static function normalizeMappedValue(string $key, mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_string($value) => $value,
            $value instanceof Stringable => (string) $value,
            default => throw InvalidMappedProperties::unsupportedValue($key, $value),
        };
    }
}
