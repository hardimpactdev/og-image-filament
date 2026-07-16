<?php

declare(strict_types=1);

namespace HardImpact\OgImageFilament\Settings;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use HardImpact\OgImageFilament\OgImageFilamentPlugin;
use HardImpact\OgImageFilament\Properties\PropertyFactory;
use HardImpact\OgImageFilament\Properties\PropertyType;
use HardImpact\OgImageFilament\Sources\ResourceSource;

final readonly class SettingsForm
{
    public function __construct(private OgImageFilamentPlugin $plugin) {}

    public static function resourceStateKey(string $resource): string
    {
        return 'resource_'.hash('sha256', $resource);
    }

    public function configure(Schema $schema): Schema
    {
        return $schema
            ->statePath('settingsData')
            ->components([
                Section::make('Properties')
                    ->description('Define the editable values available to the OG image template.')
                    ->schema([
                        Repeater::make('properties')
                            ->hiddenLabel()
                            ->schema([
                                TextInput::make('label')
                                    ->required(),
                                TextInput::make('key')
                                    ->required()
                                    ->rules(['regex:/^[a-z][a-z0-9_]*$/', 'distinct'])
                                    ->live(debounce: 300),
                                Select::make('type')
                                    ->options($this->propertyTypeOptions())
                                    ->required(),
                                TextInput::make('max_length')
                                    ->label('Maximum length')
                                    ->integer()
                                    ->minValue(1)
                                    ->nullable(),
                                Toggle::make('required')
                                    ->default(false)
                                    ->inline(false),
                            ])
                            ->columns(5)
                            ->minItems(1)
                            ->reorderable()
                            ->addActionLabel('Add property')
                            ->live(),
                    ]),
                Section::make('Resource mappings')
                    ->description('Map each property to a database column or fixed text.')
                    ->schema([
                        Tabs::make('Resources')
                            ->tabs(
                                fn (Get $get): array => $this->mappingTabs(
                                    is_array($get('properties')) ? $get('properties') : [],
                                ),
                            )
                            ->vertical()
                            ->contained(false)
                            ->extraAttributes([
                                'data-og-resource-tabs' => true,
                                'style' => 'margin-block: -1.5rem; margin-inline-start: -1.5rem;',
                            ]),
                    ]),
            ]);
    }

    /**
     * @return array{
     *     properties: array<int, array{
     *         key: string,
     *         label: string,
     *         type: string,
     *         required: bool,
     *         max_length: ?int
     *     }>,
     *     mappings: array<string, array<string, array{
     *         source: string,
     *         column: ?string,
     *         static: ?string
     *     }>>
     * }
     */
    public function state(OgImageConfiguration $configuration): array
    {
        $mappings = [];

        foreach ($configuration->mappings as $resource => $resourceMappings) {
            $resourceKey = self::resourceStateKey($resource);

            foreach ($resourceMappings as $key => $mapping) {
                $source = MappingSource::from($mapping['source']);

                $mappings[$resourceKey][$key] = [
                    'source' => $source->value,
                    'column' => $source === MappingSource::Column ? $mapping['value'] : null,
                    'static' => $source === MappingSource::StaticText ? $mapping['value'] : null,
                ];
            }
        }

        return [
            'properties' => PropertyFactory::definitions(array_values($configuration->properties)),
            'mappings' => $mappings,
        ];
    }

    /**
     * @param  array<array-key, mixed>  $propertyDefinitions
     * @param  array<string, mixed>  $formMappings
     * @return array<string, array<string, array{source: string, value: string}>>
     */
    public function mappings(array $propertyDefinitions, array $formMappings): array
    {
        $properties = $this->validPropertyDefinitions($propertyDefinitions);
        $mappings = [];

        foreach ($this->plugin->getSources() as $resource => $source) {
            $resourceMappings = $formMappings[self::resourceStateKey($resource)] ?? null;

            if (! is_array($resourceMappings)) {
                continue;
            }

            foreach ($properties as $key => $property) {
                $mapping = $resourceMappings[$key] ?? null;

                if (! is_array($mapping) || ! is_string($mapping['source'] ?? null)) {
                    continue;
                }

                $sourceType = MappingSource::tryFrom($mapping['source']);

                if ($sourceType === null) {
                    continue;
                }

                $value = match ($sourceType) {
                    MappingSource::Column => $mapping['column'] ?? null,
                    MappingSource::StaticText => $mapping['static'] ?? null,
                };

                if (! is_string($value)) {
                    continue;
                }

                $mappings[$source->getKey()][$property['key']] = [
                    'source' => $sourceType->value,
                    'value' => $value,
                ];
            }
        }

        return $mappings;
    }

    /**
     * @param  array<array-key, mixed>  $propertyDefinitions
     * @return array<int, Component>
     */
    private function mappingTabs(array $propertyDefinitions): array
    {
        $properties = $this->validPropertyDefinitions($propertyDefinitions);

        return array_map(
            fn (ResourceSource $source): Tab => Tab::make($source->getLabel())
                ->schema([
                    Section::make($source->getLabel())
                        ->description($source->getKey())
                        ->statePath('mappings.'.self::resourceStateKey($source->getKey()))
                        ->schema($this->mappingFields($source, $properties))
                        ->contained(false),
                ]),
            array_values($this->plugin->getAccessibleSources()),
        );
    }

    /**
     * @param  array<string, array{key: string, label: string}>  $properties
     * @return array<int, Component>
     */
    private function mappingFields(ResourceSource $source, array $properties): array
    {
        $fields = [];

        foreach ($properties as $property) {
            $fields[] = Fieldset::make($property['label'])
                ->statePath($property['key'])
                ->schema([
                    Select::make('source')
                        ->label('Source')
                        ->options([
                            MappingSource::Column->value => 'Model column',
                            MappingSource::StaticText->value => 'Static text',
                        ])
                        ->placeholder('None')
                        ->live(),
                    Select::make('column')
                        ->options($source->getModelColumns())
                        ->searchable()
                        ->required(fn (Get $get): bool => $get('source') === MappingSource::Column->value)
                        ->visible(fn (Get $get): bool => $get('source') === MappingSource::Column->value),
                    TextInput::make('static')
                        ->label('Static text')
                        ->required(fn (Get $get): bool => $get('source') === MappingSource::StaticText->value)
                        ->visible(fn (Get $get): bool => $get('source') === MappingSource::StaticText->value),
                ])
                ->columns(2);
        }

        if ($fields === []) {
            return [
                Section::make()
                    ->description('Add a valid property before configuring mappings.'),
            ];
        }

        return $fields;
    }

    /**
     * @param  array<array-key, mixed>  $definitions
     * @return array<string, array{key: string, label: string}>
     */
    private function validPropertyDefinitions(array $definitions): array
    {
        $properties = [];

        foreach ($definitions as $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $key = $definition['key'] ?? null;
            $label = $definition['label'] ?? null;

            if (
                ! is_string($key)
                || preg_match('/^[a-z][a-z0-9_]*$/', $key) !== 1
                || ! is_string($label)
                || trim($label) === ''
                || array_key_exists($key, $properties)
            ) {
                continue;
            }

            $properties[$key] = [
                'key' => $key,
                'label' => trim($label),
            ];
        }

        return $properties;
    }

    /**
     * @return array<string, string>
     */
    private function propertyTypeOptions(): array
    {
        return [
            PropertyType::Text->value => 'Text',
            PropertyType::Textarea->value => 'Textarea',
            PropertyType::Url->value => 'URL',
        ];
    }
}
