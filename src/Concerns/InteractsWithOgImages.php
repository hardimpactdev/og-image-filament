<?php

declare(strict_types=1);

namespace HardImpact\OgImageFilament\Concerns;

use HardImpact\OgImageFilament\Contracts\GeneratesOgImages;
use HardImpact\OgImageFilament\Jobs\GenerateOgImage;
use Illuminate\Database\Eloquent\Model;
use LogicException;

trait InteractsWithOgImages
{
    public static function bootInteractsWithOgImages(): void
    {
        static::saved(function (Model $model): void {
            if (! $model instanceof GeneratesOgImages) {
                throw new LogicException('Models using InteractsWithOgImages must implement GeneratesOgImages.');
            }

            $panelId = $model->ogImagePanelId();
            $source = $model->ogImageSource();
            $record = $model->getRouteKey();

            if (! is_int($record) && ! is_string($record)) {
                throw new LogicException('OG image records must have an integer or string route key.');
            }

            $model->getConnection()->afterCommit(
                static fn () => GenerateOgImage::dispatch($panelId, $source, $record)->afterCommit(),
            );
        });
    }
}
