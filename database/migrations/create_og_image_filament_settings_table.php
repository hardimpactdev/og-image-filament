<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('og_image_filament_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('panel_id')->unique();
            $table->json('properties');
            $table->json('mappings');
            $table->timestamps();
        });
    }
};
