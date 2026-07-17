<?php

declare(strict_types=1);

namespace HardImpact\OgImageFilament\Rendering;

use Closure;
use HardImpact\OgImageFilament\PropertyBag;
use RuntimeException;
use Spatie\Browsershot\Browsershot;

final readonly class OgImageRenderer
{
    /** @param null|Closure(string): Browsershot $browsershotFactory */
    public function __construct(private ?Closure $browsershotFactory = null) {}

    /** @param view-string $view */
    public function render(string $view, PropertyBag $properties): string
    {
        $card = view($view, ['properties' => $properties])->render();
        $html = <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=1200, initial-scale=1">
                <style>
                    html, body { margin: 0; width: 1200px; height: 630px; overflow: hidden; }
                </style>
            </head>
            <body>{$card}</body>
            </html>
            HTML;
        $browsershot = $this->browsershotFactory === null
            ? Browsershot::html($html)
            : ($this->browsershotFactory)($html);

        $browsershot->windowSize(1200, 630);

        $nodeBinary = config('og-image-filament.node_binary');

        if (is_string($nodeBinary) && trim($nodeBinary) !== '') {
            $browsershot->setNodeBinary($nodeBinary);
        }

        $chromePath = config('og-image-filament.chrome_path');

        if (is_string($chromePath) && trim($chromePath) !== '') {
            $browsershot->setChromePath($chromePath);
        }

        $temporaryBase = tempnam(sys_get_temp_dir(), 'og-image-');

        if ($temporaryBase === false) {
            throw new RuntimeException('A temporary OG image path could not be created.');
        }

        $temporaryPng = $temporaryBase.'.png';

        try {
            @unlink($temporaryBase);
            $browsershot->save($temporaryPng);
            $png = file_get_contents($temporaryPng);

            if ($png === false) {
                throw new RuntimeException('The generated OG image could not be read.');
            }

            return $png;
        } finally {
            if (file_exists($temporaryBase)) {
                unlink($temporaryBase);
            }

            if (file_exists($temporaryPng)) {
                unlink($temporaryPng);
            }
        }
    }
}
