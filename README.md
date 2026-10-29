# OG Image Filament

[![Tests](https://github.com/hardimpactdev/og-image-filament/actions/workflows/run-tests.yml/badge.svg)](https://github.com/hardimpactdev/og-image-filament/actions/workflows/run-tests.yml)

Generate deterministic 1200 × 630 Open Graph images from Filament resource records.

Every resource owns its Blade template, DTO resolver, and storage path in application code. The Filament page is a read-only previewer with source and entry selection plus manual regeneration.

## Installation

```bash
composer require hardimpactdev/og-image-filament
php artisan migrate
```

Create an application DTO:

```php
<?php

declare(strict_types=1);

namespace App\Data\OgImages;

use App\Models\Article;

final readonly class ArticleOgImageData
{
    public function __construct(
        public string $label,
        public string $title,
        public string $description,
        public string $url,
    ) {}

    public static function from(Article $article): self
    {
        return new self(
            label: 'Article',
            title: $article->title,
            description: $article->description,
            url: url("/articles/{$article->slug}"),
        );
    }
}
```

Register the plugin on a Filament panel:

```php
use App\Data\OgImages\ArticleOgImageData;
use App\Filament\Resources\ArticleResource;
use App\Models\Article;
use Filament\Panel;
use HardImpact\OgImageFilament\OgImageFilamentPlugin;
use HardImpact\OgImageFilament\Sources\ResourceSource;

public function panel(Panel $panel): Panel
{
    return $panel->plugin(
        OgImageFilamentPlugin::make()
            ->sources([
                ResourceSource::make(ArticleResource::class)
                    ->template('og-images.articles')
                    ->dataUsing(
                        fn (Article $article): ArticleOgImageData => ArticleOgImageData::from($article),
                    )
                    ->pathUsing(
                        fn (Article $article): string => "articles/{$article->getKey()}.png",
                    ),
            ]),
    );
}
```

The package passes the resolved DTO to Blade as `$data`:

```blade
<article style="width: 1200px; height: 630px">
    <p>{{ $data->label }}</p>
    <h1>{{ $data->title }}</h1>
    <p>{{ $data->description }}</p>
    <p>{{ $data->url }}</p>
</article>
```

Templates and DTOs belong to the consuming application, so changes remain versioned in Git and deploy with the app. Missing templates, resolvers, paths, and render failures throw immediately.

Models that implement `GeneratesOgImages` and use `InteractsWithOgImages` regenerate synchronously after each committed save. Deleting the model removes its PNG before the database row is deleted. Generation and deletion failures propagate to the caller.

## Configuration

```dotenv
OG_IMAGE_DISK=public
OG_IMAGE_DIRECTORY=og-images
OG_IMAGE_NODE_BINARY=/path/to/node
OG_IMAGE_CHROME_PATH=/path/to/chrome
OG_IMAGE_NO_SANDBOX=false
```

Set `OG_IMAGE_NO_SANDBOX=true` only when Chromium runs as root inside a trusted container.

## Development

```bash
composer test
composer analyse
vendor/bin/pint --test
```

## License

MIT

## Browser-free renderer

Select `satori` in the application's `config/og-image-filament.php`. The existing
Browsershot backend remains the default for other applications. A selected
Satori backend never falls back to Chrome.

Install Node.js 24+ and the tested runtime packages in the consuming app:

```sh
npm install --save-exact satori@0.63.2 @resvg/resvg-wasm@2.6.2
```

Satori pins an affected fflate release. Add this scoped npm override, then commit
the lockfile: `"overrides": { "satori": { "fflate": "0.7.5" } }`.
It fixes [GHSA-px8p-9vwx-vf98](https://github.com/advisories/GHSA-px8p-9vwx-vf98).
The package's own Node dependencies and lockfile are for its tests; Composer does
not install them in a consuming app.

```php
return [
    'renderer' => 'satori',
    'satori' => [
        'templates' => [
            'og-images.articles' => [
                'directory' => resource_path('og-images'),
                'template' => 'article',
            ],
        ],
        'node_modules' => base_path('node_modules'),
        'timeout_seconds' => 10,
    ],
];
```

Each trusted template directory contains `manifest.json`, `template.mjs` and its
bundled artwork/fonts. A manifest defines template IDs and their backgrounds:

```json
{
    "templates": { "article": { "background": "assets/article.png" } },
    "fonts": [{ "name": "CardRegular", "file": "assets/regular.ttf" }]
}
```

`template.mjs` exports `render({ template, data, background })`. It returns the
fixed Satori element tree. `background` is a data URI loaded from the registered
manifest. Fonts use their manifest names at weight 400; instantiate variable
font weights ahead of time and give each instance its own family name. Artwork
must be a 1200×630 PNG. Keep the font's redistribution license with the assets.

Record DTOs may contain only `label`, `title`, `description` and `url` strings.
Missing fields become empty strings. Character limits are 80, 512, 2000 and 2048
respectively; the full UTF-8 JSON request is at most 16 KiB. The URL field is
printed text, never a resource to fetch. No record data selects a module, asset,
font, path, HTML fragment or script. Treat the manifest and module as trusted
application code, exactly like PHP source.

Every render starts a fresh Node process as the application account. Its
environment omits inherited credentials. Node permissions allow reads only in
the renderer, template and node_modules directories; file writes, child
processes, workers and native addons are denied. Node 24 permissions do not
isolate networking: the renderer rejects `fetch`, and templates must use bundled
assets only. These controls and WASM are not an OS sandbox for hostile template
code. Applications must not accept templates or manifests from users.

A per-Instance file lock serializes calls. The configured deadline includes lock
waiting and process execution (1–30 seconds). Each font is at most 1 MiB, with at
most eight fonts; the background is at most 3 MiB, SVG 8 MiB, PNG 4 MiB and stderr
8 KiB. The V8 heap limit is 256 MiB; WASM/image memory is additional. The child
exits after each render. Failures propagate before the manager replaces the
previous image. The PNG/storage contract and synchronous save behavior remain.
