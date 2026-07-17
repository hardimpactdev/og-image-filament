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
```

## Development

```bash
composer test
composer analyse
vendor/bin/pint --test
```

## License

MIT
