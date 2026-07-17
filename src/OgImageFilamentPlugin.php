<?php

declare(strict_types=1);

namespace HardImpact\OgImageFilament;

use BackedEnum;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Assets\Js;
use HardImpact\OgImageFilament\Exceptions\InvalidPluginConfiguration;
use HardImpact\OgImageFilament\Filament\Pages\OgImageGenerator;
use HardImpact\OgImageFilament\Properties\Property;
use HardImpact\OgImageFilament\Sources\ResourceSource;
use UnitEnum;

final class OgImageFilamentPlugin implements Plugin
{
    /** @var view-string */
    private string $template = 'og-image-filament::card';

    /** @var array<int, mixed> */
    private array $configuredProperties = [];

    /** @var array<int, mixed> */
    private array $configuredSources = [];

    private string $configuredNavigationLabel = 'OG image generator';

    private string|UnitEnum|null $configuredNavigationGroup = null;

    private string|BackedEnum|null $configuredNavigationIcon = 'heroicon-o-photo';

    private ?int $configuredNavigationSort = null;

    private string $configuredSlug = 'og-image-generator';

    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'og-image-filament';
    }

    public function register(Panel $panel): void
    {
        $this->validateConfiguration();
        $assetPath = __DIR__.'/../resources/dist/og-image-filament.iife.js';
        $assetHash = hash_file('sha256', $assetPath);

        if (! is_string($assetHash)) {
            throw new \RuntimeException('The OG image Filament asset could not be fingerprinted.');
        }

        $panel
            ->pages([OgImageGenerator::class])
            ->assets([
                Js::make(
                    'og-image-filament-'.substr($assetHash, 0, 12),
                    $assetPath,
                ),
            ], package: 'hardimpactdev/og-image-filament');
    }

    public function boot(Panel $panel): void {}

    /** @param view-string $view */
    public function template(string $view): self
    {
        $this->template = $view;

        return $this;
    }

    /**
     * @param  array<int, mixed>  $properties
     */
    public function properties(array $properties): self
    {
        $this->configuredProperties = $properties;

        return $this;
    }

    /**
     * @param  array<int, mixed>  $sources
     */
    public function sources(array $sources): self
    {
        $this->configuredSources = $sources;

        return $this;
    }

    public function navigationLabel(string $label): self
    {
        $this->configuredNavigationLabel = $label;

        return $this;
    }

    public function navigationGroup(string|UnitEnum|null $group): self
    {
        $this->configuredNavigationGroup = $group;

        return $this;
    }

    public function navigationIcon(string|BackedEnum|null $icon): self
    {
        $this->configuredNavigationIcon = $icon;

        return $this;
    }

    public function navigationSort(?int $sort): self
    {
        $this->configuredNavigationSort = $sort;

        return $this;
    }

    public function slug(string $slug): self
    {
        $this->configuredSlug = trim($slug, '/');

        return $this;
    }

    /** @return view-string */
    public function getTemplate(): string
    {
        if (trim($this->template) === '') {
            throw InvalidPluginConfiguration::emptyTemplate();
        }

        return $this->template;
    }

    /**
     * @return array<string, Property>
     */
    public function getProperties(): array
    {
        if ($this->configuredProperties === []) {
            throw InvalidPluginConfiguration::missingProperties();
        }

        $properties = [];

        foreach ($this->configuredProperties as $property) {
            if (! $property instanceof Property) {
                throw InvalidPluginConfiguration::invalidProperty($property);
            }

            if (array_key_exists($property->key, $properties)) {
                throw InvalidPluginConfiguration::duplicateProperty($property->key);
            }

            $properties[$property->key] = $property;
        }

        return $properties;
    }

    /**
     * @return array<string, ResourceSource>
     */
    public function getSources(): array
    {
        if ($this->configuredSources === []) {
            throw InvalidPluginConfiguration::missingSources();
        }

        $sources = [];

        foreach ($this->configuredSources as $source) {
            if (! $source instanceof ResourceSource) {
                throw InvalidPluginConfiguration::invalidSource($source);
            }

            if (array_key_exists($source->getKey(), $sources)) {
                throw InvalidPluginConfiguration::duplicateSource($source->getKey());
            }

            $sources[$source->getKey()] = $source;
        }

        return $sources;
    }

    /**
     * @return array<string, ResourceSource>
     */
    public function getAccessibleSources(): array
    {
        return array_filter(
            $this->getSources(),
            fn (ResourceSource $source): bool => $source->isAccessible(),
        );
    }

    public function getNavigationLabel(): string
    {
        return $this->configuredNavigationLabel;
    }

    public function getNavigationGroup(): string|UnitEnum|null
    {
        return $this->configuredNavigationGroup;
    }

    public function getNavigationIcon(): string|BackedEnum|null
    {
        return $this->configuredNavigationIcon;
    }

    public function getNavigationSort(): ?int
    {
        return $this->configuredNavigationSort;
    }

    public function getSlug(): string
    {
        if ($this->configuredSlug === '') {
            throw InvalidPluginConfiguration::emptySlug();
        }

        return $this->configuredSlug;
    }

    private function validateConfiguration(): void
    {
        $this->getTemplate();
        $this->getProperties();
        $this->getSources();
        $this->getSlug();
    }
}
