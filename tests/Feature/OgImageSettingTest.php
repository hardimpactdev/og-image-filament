<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('stores one OG image configuration per Filament panel', function (): void {
    expect(Schema::hasTable('og_image_filament_settings'))->toBeTrue();

    DB::table('og_image_filament_settings')->insert([
        'panel_id' => 'admin',
        'properties' => '[]',
        'mappings' => '{}',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => DB::table('og_image_filament_settings')->insert([
        'panel_id' => 'admin',
        'properties' => '[]',
        'mappings' => '{}',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
