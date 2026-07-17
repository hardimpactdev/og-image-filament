# OG Image Filament

[![Tests](https://github.com/hardimpactdev/og-image-filament/actions/workflows/run-tests.yml/badge.svg)](https://github.com/hardimpactdev/og-image-filament/actions/workflows/run-tests.yml)

Build editable 1200 × 630 Open Graph images from records exposed by Filament resources.

Applications define the initial editable properties, allowed Filament resources, and Blade template. Administrators can then maintain the property schema and column/static mappings from the same Filament page. The package stores one JSON configuration row per panel and provides browser-side PNG export.

## Installation

```bash
composer require hardimpactdev/og-image-filament
php artisan migrate
php artisan filament:assets
```

Register the plugin on a Filament panel:

```php
use App\Filament\Resources\ArticleResource;
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
                        ->template('og-images.articles')
                        ->defaultMappings([
                            'label' => ['source' => 'static', 'value' => 'Article'],
                            'title' => ['source' => 'column', 'value' => 'title'],
                            'description' => ['source' => 'column', 'value' => 'description'],
                            'url' => ['source' => 'column', 'value' => 'slug'],
                        ]),
                ]),
        );
}
```

The plugin template remains the fallback for every source. Call `template()` on a resource source when that resource should render a different Blade view. PHP source templates are not stored in the editable database configuration.

The PHP property and mapping definitions are used until the first configuration save. After that, the database row for the panel takes precedence.

Open the **Configure** tab on the OG image generator page to:

- Add, remove, or reorder text, textarea, and URL properties.
- Mark properties as required and set maximum lengths.
- Map each resource property to a model database column or static text.

Relationships, accessors, computed values, URL composition, and fallbacks are intentionally not supported yet. Missing model values remain empty and editable in the **Generate** tab.

The migration is loaded by the package. To publish a copy into the application instead:

```bash
php artisan vendor:publish --tag=og-image-filament-migrations
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
