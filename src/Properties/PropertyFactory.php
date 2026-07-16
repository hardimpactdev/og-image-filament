<?php

declare(strict_types=1);

namespace HardImpact\OgImageFilament\Properties;

use HardImpact\OgImageFilament\Exceptions\InvalidPropertyConfiguration;

final class PropertyFactory
{
    /**
     * @param  array<int, mixed>  $properties
     * @return array<int, array{
     *     key: string,
     *     label: string,
     *     type: string,
     *     required: bool,
     *     max_length: ?int
     * }>
     */
    public static function definitions(array $properties): array
    {
        return array_map(
            function (mixed $property): array {
                if (! $property instanceof Property) {
                    throw InvalidPropertyConfiguration::invalidDefinition();
                }

                return [
                    'key' => $property->key,
                    'label' => $property->getLabel(),
                    'type' => $property->getType()->value,
                    'required' => $property->isRequired(),
                    'max_length' => $property->getMaximumLength(),
                ];
            },
            $properties,
        );
    }

    /**
     * @param  array<int, mixed>  $definitions
     * @return array<string, Property>
     */
    public static function fromDefinitions(array $definitions): array
    {
        $properties = [];

        foreach ($definitions as $definition) {
            if (! is_array($definition)) {
                throw InvalidPropertyConfiguration::invalidDefinition();
            }

            $key = $definition['key'] ?? null;
            $label = $definition['label'] ?? null;
            $type = $definition['type'] ?? null;
            $required = $definition['required'] ?? null;
            $maximumLength = $definition['max_length'] ?? null;

            $required = match ($required) {
                true, 1, '1' => true,
                false, 0, '0' => false,
                default => null,
            };
            $maximumLength = match (true) {
                $maximumLength === null, $maximumLength === '' => null,
                is_int($maximumLength) => $maximumLength,
                is_float($maximumLength) && floor($maximumLength) === $maximumLength => (int) $maximumLength,
                is_string($maximumLength) && ctype_digit($maximumLength) => (int) $maximumLength,
                default => false,
            };

            if (
                ! is_string($key)
                || ! is_string($label)
                || ! is_string($type)
                || $required === null
                || $maximumLength === false
            ) {
                throw InvalidPropertyConfiguration::invalidDefinition();
            }

            if (array_key_exists($key, $properties)) {
                throw InvalidPropertyConfiguration::duplicateKey($key);
            }

            $propertyType = PropertyType::tryFrom($type);

            if ($propertyType === null) {
                throw InvalidPropertyConfiguration::invalidType($type);
            }

            $properties[$key] = $propertyType
                ->make($key)
                ->label($label)
                ->required($required)
                ->maxLength($maximumLength);
        }

        return $properties;
    }
}
