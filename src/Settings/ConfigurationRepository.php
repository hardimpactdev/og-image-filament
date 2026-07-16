<?php

declare(strict_types=1);

namespace HardImpact\OgImageFilament\Settings;

use HardImpact\OgImageFilament\Exceptions\InvalidSettings;
use HardImpact\OgImageFilament\Models\OgImageSetting;
use HardImpact\OgImageFilament\OgImageFilamentPlugin;
use HardImpact\OgImageFilament\Properties\Property;
use HardImpact\OgImageFilament\Properties\PropertyFactory;
use HardImpact\OgImageFilament\Sources\ResourceSource;
use Illuminate\Support\Facades\DB;

final class ConfigurationRepository
{
    public function forPanel(string $panelId, OgImageFilamentPlugin $plugin): OgImageConfiguration
    {
        $setting = OgImageSetting::query()
            ->where('panel_id', $panelId)
            ->first();

        if ($setting === null) {
            return new OgImageConfiguration(
                properties: $plugin->getProperties(),
                mappings: $this->defaultMappings($plugin),
            );
        }

        $properties = PropertyFactory::fromDefinitions($setting->properties);

        return new OgImageConfiguration(
            properties: $properties,
            mappings: $this->normalizeMappings(
                mappings: $setting->mappings,
                properties: $properties,
                plugin: $plugin,
            ),
        );
    }

    /**
     * @param  array<int, mixed>  $propertyDefinitions
     * @param  array<string, mixed>  $mappings
     */
    public function save(
        string $panelId,
        array $propertyDefinitions,
        array $mappings,
        OgImageFilamentPlugin $plugin,
    ): OgImageConfiguration {
        $properties = PropertyFactory::fromDefinitions(array_values($propertyDefinitions));
        $normalizedDefinitions = PropertyFactory::definitions(array_values($properties));
        $normalizedMappings = $this->normalizeMappings($mappings, $properties, $plugin);

        DB::transaction(function () use ($panelId, $normalizedDefinitions, $normalizedMappings): void {
            OgImageSetting::query()->updateOrCreate(
                ['panel_id' => $panelId],
                [
                    'properties' => $normalizedDefinitions,
                    'mappings' => $normalizedMappings,
                ],
            );
        });

        return new OgImageConfiguration(
            properties: $properties,
            mappings: $normalizedMappings,
        );
    }

    /**
     * @return array<string, array<string, array{source: string, value: string}>>
     */
    private function defaultMappings(OgImageFilamentPlugin $plugin): array
    {
        $mappings = [];

        foreach ($plugin->getSources() as $key => $source) {
            $sourceMappings = $source->getDefaultMappings();

            if ($sourceMappings !== []) {
                $mappings[$key] = $sourceMappings;
            }
        }

        return $this->normalizeMappings(
            mappings: $mappings,
            properties: $plugin->getProperties(),
            plugin: $plugin,
        );
    }

    /**
     * @param  array<string, mixed>  $mappings
     * @param  array<string, Property>  $properties
     * @return array<string, array<string, array{source: string, value: string}>>
     */
    private function normalizeMappings(
        array $mappings,
        array $properties,
        OgImageFilamentPlugin $plugin,
    ): array {
        $normalizedMappings = [];

        foreach ($plugin->getSources() as $resource => $source) {
            $resourceMappings = $mappings[$resource] ?? null;

            if (! is_array($resourceMappings)) {
                continue;
            }

            $normalizedResourceMappings = $this->normalizeResourceMappings(
                resourceMappings: $resourceMappings,
                properties: $properties,
                source: $source,
            );

            if ($normalizedResourceMappings !== []) {
                $normalizedMappings[$resource] = $normalizedResourceMappings;
            }
        }

        return $normalizedMappings;
    }

    /**
     * @param  array<array-key, mixed>  $resourceMappings
     * @param  array<string, Property>  $properties
     * @return array<string, array{source: string, value: string}>
     */
    private function normalizeResourceMappings(
        array $resourceMappings,
        array $properties,
        ResourceSource $source,
    ): array {
        $normalizedMappings = [];
        $columns = $source->getModelColumns();

        foreach ($properties as $key => $property) {
            $mapping = $resourceMappings[$key] ?? null;

            if ($mapping === null) {
                continue;
            }

            if (
                ! is_array($mapping)
                || ! is_string($mapping['source'] ?? null)
                || ! is_string($mapping['value'] ?? null)
            ) {
                throw InvalidSettings::invalidMapping($source->getKey(), $property->key);
            }

            $mappingSource = MappingSource::tryFrom($mapping['source']);

            if ($mappingSource === null) {
                throw InvalidSettings::invalidMapping($source->getKey(), $property->key);
            }

            $value = $mapping['value'];

            if ($mappingSource === MappingSource::Column && ! array_key_exists($value, $columns)) {
                throw InvalidSettings::unknownColumn($source->getKey(), $property->key, $value);
            }

            $normalizedMappings[$key] = [
                'source' => $mappingSource->value,
                'value' => $value,
            ];
        }

        return $normalizedMappings;
    }
}
