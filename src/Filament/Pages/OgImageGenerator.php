<?php

declare(strict_types=1);

namespace HardImpact\OgImageFilament\Filament\Pages;

use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use HardImpact\OgImageFilament\Jobs\GenerateOgImage;
use HardImpact\OgImageFilament\OgImageFilamentPlugin;
use HardImpact\OgImageFilament\Properties\Property;
use HardImpact\OgImageFilament\PropertyBag;
use HardImpact\OgImageFilament\Settings\ConfigurationRepository;
use HardImpact\OgImageFilament\Settings\OgImageConfiguration;
use HardImpact\OgImageFilament\Settings\SettingsForm;
use HardImpact\OgImageFilament\Sources\ResourceSource;
use Illuminate\Contracts\Support\Htmlable;
use Throwable;
use UnitEnum;

final class OgImageGenerator extends Page
{
    protected string $view = 'og-image-filament::filament.pages.generator';

    /**
     * @var array{
     *     source: ?string,
     *     entry: int|string|null,
     *     properties: array<string, mixed>
     * }
     */
    public array $data = [
        'source' => null,
        'entry' => null,
        'properties' => [],
    ];

    /**
     * @var array{
     *     properties: array<array-key, mixed>,
     *     mappings: array<string, mixed>
     * }
     */
    public array $settingsData = [
        'properties' => [],
        'mappings' => [],
    ];

    public string $activeTab = 'generate';

    public function mount(): void
    {
        $configuration = $this->configuration();

        $this->settingsData = $this->settingsFormBuilder()->state($configuration);
        $this->resetErrorBag();
        $this->generatorForm()->fill($this->data);
        $this->settingsFormSchema()->fill($this->settingsData);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Select::make('source')
                    ->label('Source')
                    ->options(fn (): array => $this->sourceOptions())
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (): void {
                        $this->resetSelectedEntry();
                    }),
                Select::make('entry')
                    ->label('Entry')
                    ->searchable()
                    ->required()
                    ->disabled(fn (Get $get): bool => blank($get('source')))
                    ->getSearchResultsUsing(
                        fn (Get $get, ?string $search): array => $this->entryOptions(
                            $this->normalizeSourceKey($get('source')),
                            $search,
                        ),
                    )
                    ->getOptionLabelUsing(
                        fn (Get $get, int|string|null $value): ?string => $this->entryLabel(
                            $this->normalizeSourceKey($get('source')),
                            $value,
                        ),
                    )
                    ->live()
                    ->afterStateUpdated(function (int|string|null $state): void {
                        $this->populateFromEntry($state);
                    }),
                Section::make('Properties')
                    ->statePath('properties')
                    ->schema(fn (): array => $this->propertyComponents())
                    ->visible(fn (Get $get): bool => filled($get('entry'))),
            ]);
    }

    public function settingsForm(Schema $schema): Schema
    {
        return $this->settingsFormBuilder()->configure($schema);
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return self::getPlugin($panel)->getSlug();
    }

    public static function getNavigationLabel(): string
    {
        return self::getPlugin()->getNavigationLabel();
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return self::getPlugin()->getNavigationGroup();
    }

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return self::getPlugin()->getNavigationIcon();
    }

    public static function getNavigationSort(): ?int
    {
        return self::getPlugin()->getNavigationSort();
    }

    public function getTitle(): string
    {
        return self::getPlugin()->getNavigationLabel();
    }

    public function previewProperties(): PropertyBag
    {
        return PropertyBag::fromState(
            array_values($this->configuration()->properties),
            $this->data['properties'],
        );
    }

    public function previewTemplate(): string
    {
        return $this->plugin()->getTemplate();
    }

    public function generate(): void
    {
        $state = $this->generatorForm()->getState();
        $source = $state['source'] ?? null;
        $entry = $state['entry'] ?? null;
        $properties = $state['properties'] ?? [];

        if (
            ! is_string($source)
            || (! is_int($entry) && ! is_string($entry))
            || ! is_array($properties)
        ) {
            throw new \LogicException('The validated OG image generator state is invalid.');
        }

        GenerateOgImage::dispatch(
            panelId: $this->panelId(),
            source: $source,
            record: $entry,
            overrides: $properties,
        )->afterCommit();

        Notification::make()
            ->title('OG image generation queued')
            ->success()
            ->send();
    }

    public function saveSettings(): void
    {
        $state = $this->settingsFormSchema()->getState();
        $propertyDefinitions = is_array($state['properties'] ?? null)
            ? $state['properties']
            : [];
        $formMappings = is_array($state['mappings'] ?? null)
            ? $state['mappings']
            : [];

        $configuration = $this->configurationRepository()->save(
            panelId: $this->panelId(),
            propertyDefinitions: $propertyDefinitions,
            mappings: $this->settingsFormBuilder()->mappings($propertyDefinitions, $formMappings),
            plugin: $this->plugin(),
        );

        $this->settingsData = $this->settingsFormBuilder()->state($configuration);
        $this->settingsFormSchema()->fill($this->settingsData);
        $this->resetSelectedEntry();
        $this->generatorForm()->fill($this->data);

        Notification::make()
            ->title('OG image configuration saved')
            ->success()
            ->send();
    }

    /**
     * @return array<string, string>
     */
    private function sourceOptions(): array
    {
        return array_map(
            fn (ResourceSource $source): string => $source->getLabel(),
            $this->plugin()->getAccessibleSources(),
        );
    }

    /**
     * @return array<int|string, string>
     */
    private function entryOptions(?string $source, ?string $search): array
    {
        return $this->selectedSource($source)?->search($search) ?? [];
    }

    private function entryLabel(?string $source, int|string|null $entry): ?string
    {
        if ($entry === null) {
            return null;
        }

        $selectedSource = $this->selectedSource($source);
        $record = $selectedSource?->resolveRecord($entry);

        if ($selectedSource === null || $record === null) {
            return null;
        }

        return $selectedSource->getRecordTitle($record);
    }

    private function resetSelectedEntry(): void
    {
        $this->data['entry'] = null;
        $this->data['properties'] = [];
        $this->resetValidation();
    }

    private function populateFromEntry(int|string|null $entry): void
    {
        $this->resetValidation();

        if ($entry === null) {
            $this->data['properties'] = [];

            return;
        }

        try {
            $source = $this->selectedSource();
            $record = $source?->resolveRecord($entry);

            if ($source === null || $record === null) {
                throw new \RuntimeException('The selected OG image entry is unavailable.');
            }

            $configuration = $this->configuration();
            $properties = PropertyBag::fromState(
                array_values($configuration->properties),
                $configuration->mapRecord($source, $record),
            );

            $this->data['properties'] = $properties->all();
        } catch (Throwable $exception) {
            $this->data['properties'] = [];

            report($exception);

            Notification::make()
                ->title('The entry could not be loaded')
                ->body('Choose another entry or check the source mapping.')
                ->danger()
                ->send();
        }
    }

    /**
     * @return array<int, Component>
     */
    private function propertyComponents(): array
    {
        return array_map(
            fn (Property $property): Component => $property->formComponent(),
            array_values($this->configuration()->properties),
        );
    }

    private function selectedSource(?string $key = null): ?ResourceSource
    {
        $key ??= $this->data['source'];

        if ($key === null) {
            return null;
        }

        return $this->plugin()->getAccessibleSources()[$key] ?? null;
    }

    private function plugin(): OgImageFilamentPlugin
    {
        return self::getPlugin();
    }

    private function configuration(): OgImageConfiguration
    {
        return $this->configurationRepository()->forPanel(
            panelId: $this->panelId(),
            plugin: $this->plugin(),
        );
    }

    private function configurationRepository(): ConfigurationRepository
    {
        return resolve(ConfigurationRepository::class);
    }

    private function settingsFormBuilder(): SettingsForm
    {
        return new SettingsForm($this->plugin());
    }

    private function panelId(): string
    {
        return (Filament::getCurrentPanel() ?? Filament::getDefaultPanel())->getId();
    }

    private function generatorForm(): Schema
    {
        return $this->getSchema('form')
            ?? throw new \LogicException('The OG image generator form schema is unavailable.');
    }

    private function settingsFormSchema(): Schema
    {
        return $this->getSchema('settingsForm')
            ?? throw new \LogicException('The OG image settings form schema is unavailable.');
    }

    private function normalizeSourceKey(mixed $source): ?string
    {
        return is_string($source) ? $source : null;
    }

    private static function getPlugin(?Panel $panel = null): OgImageFilamentPlugin
    {
        $panel ??= Filament::getCurrentPanel() ?? Filament::getDefaultPanel();
        $plugin = $panel->getPlugin('og-image-filament');

        if (! $plugin instanceof OgImageFilamentPlugin) {
            throw new \LogicException('The registered og-image-filament plugin has an unexpected type.');
        }

        return $plugin;
    }
}
