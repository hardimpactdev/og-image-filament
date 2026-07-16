# OG Image Filament

[![Tests](https://github.com/hardimpactdev/og-image-filament/actions/workflows/run-tests.yml/badge.svg)](https://github.com/hardimpactdev/og-image-filament/actions/workflows/run-tests.yml)

Build editable 1200 × 630 Open Graph images from records exposed by Filament resources.

Applications define the editable properties, resource mappings, and Blade template. The package provides the authenticated Filament workflow and browser-side PNG export.

## Installation

```bash
composer require hardimpactdev/og-image-filament
php artisan filament:assets
```

Register the plugin on a Filament panel:

```php
use App\Filament\Resources\ArticleResource;
use App\Models\Article;
use Filament\Panel;
use HardImpact\OgImageFilament\OgImageFilamentPlugin;
use HardImpact\OgImageFilament\Properties\TextProperty;
use HardImpact\OgImageFilament\Properties\TextareaProperty;
use HardImpact\OgImageFilament\Properties\UrlProperty;
use HardImpact\OgImageFilament\Sources\ResourceSource;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugin(
            OgImageFilamentPlugin::make()
                ->properties([
                    TextProperty::make('label')->maxLength(40),
                    TextareaProperty::make('title')->required()->maxLength(180),
                    TextareaProperty::make('description')->maxLength(160),
                    UrlProperty::make('url')->required(),
                ])
                ->sources([
                    ResourceSource::make(ArticleResource::class)
                        ->map(fn (Article $article): array => [
                            'label' => 'Article',
                            'title' => $article->title,
                            'description' => $article->description,
                            'url' => route('articles.show', $article),
                        ]),
                ]),
        );
}
```

Publish the starter Blade template:

```bash
php artisan vendor:publish --tag=og-image-filament-views
```

Customize:

```text
resources/views/vendor/og-image-filament/card.blade.php
```

## Development

```bash
composer test
composer analyse
vendor/bin/pint --test
bun run test
bun run build
```

## License

MIT
