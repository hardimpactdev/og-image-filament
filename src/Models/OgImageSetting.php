<?php

declare(strict_types=1);

namespace HardImpact\OgImageFilament\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $panel_id
 * @property array<int, mixed> $properties
 * @property array<string, mixed> $mappings
 */
final class OgImageSetting extends Model
{
    protected $table = 'og_image_filament_settings';

    protected $fillable = [
        'panel_id',
        'properties',
        'mappings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'mappings' => 'array',
        ];
    }
}
