<?php

declare(strict_types=1);

namespace HardImpact\OgImageFilament\Jobs;

use HardImpact\OgImageFilament\OgImageManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

final class GenerateOgImage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** @param null|array<array-key, mixed> $overrides */
    public function __construct(
        public readonly string $panelId,
        public readonly string $source,
        public readonly int|string $record,
        public readonly ?array $overrides = null,
    ) {}

    public function handle(OgImageManager $manager): void
    {
        $manager->generate(
            panelId: $this->panelId,
            source: $this->source,
            record: $this->record,
        );
    }
}
