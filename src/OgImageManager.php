<?php

declare(strict_types=1);

namespace HardImpact\OgImageFilament;

use Filament\PanelRegistry;
use HardImpact\OgImageFilament\Rendering\OgImageRenderer;
use HardImpact\OgImageFilament\Sources\ResourceSource;
use HardImpact\OgImageFilament\Storage\GeneratedOgImages;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final readonly class OgImageManager
{
    public function __construct(
        private OgImageRenderer $renderer,
        private GeneratedOgImages $images,
    ) {}

    public function generate(
        string $panelId,
        string $source,
        int|string $record,
    ): void {
        $plugin = $this->plugin($panelId);
        $resourceSource = $this->source($plugin, $source);
        $model = $resourceSource->resolveRecordForGeneration($record);

        if ($model === null) {
            throw new RuntimeException("OG image record [{$record}] for source [{$source}] no longer exists.");
        }

        $png = $this->renderer->render(
            $resourceSource->getTemplate(),
            $resourceSource->resolveData($model),
        );

        $this->images->replace($resourceSource->resolvePath($model), $png);
    }

    public function url(string $panelId, string $source, Model $record): ?string
    {
        $resourceSource = $this->source($this->plugin($panelId), $source);

        return $this->images->url($resourceSource->resolvePath($record));
    }

    private function plugin(string $panelId): OgImageFilamentPlugin
    {
        $panel = app(PanelRegistry::class)->get($panelId, isStrict: false);

        if ($panel === null) {
            throw new RuntimeException("Filament panel [{$panelId}] is not registered.");
        }

        $plugin = $panel->getPlugin('og-image-filament');

        if (! $plugin instanceof OgImageFilamentPlugin) {
            throw new RuntimeException("Filament panel [{$panelId}] does not register the OG image plugin.");
        }

        return $plugin;
    }

    private function source(OgImageFilamentPlugin $plugin, string $source): ResourceSource
    {
        $resourceSource = $plugin->getSources()[$source] ?? null;

        if ($resourceSource === null) {
            throw new RuntimeException("OG image source [{$source}] is not registered.");
        }

        return $resourceSource;
    }
}
