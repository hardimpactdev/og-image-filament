<?php

declare(strict_types=1);

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use HardImpact\OgImageFilament\Exceptions\InvalidPropertyConfiguration;
use HardImpact\OgImageFilament\Properties\TextareaProperty;
use HardImpact\OgImageFilament\Properties\TextProperty;
use HardImpact\OgImageFilament\Properties\UrlProperty;

describe('properties', function (): void {
    it('builds the supported Filament fields', function (): void {
        expect(TextProperty::make('label')->label('Label')->maxLength(40)->formComponent())
            ->toBeInstanceOf(TextInput::class)
            ->and(TextareaProperty::make('title')->required()->maxLength(180)->formComponent())
            ->toBeInstanceOf(Textarea::class)
            ->and(UrlProperty::make('url')->required()->formComponent())
            ->toBeInstanceOf(TextInput::class);
    });

    it('builds validation rules from its configuration', function (): void {
        expect(TextProperty::make('title')->required()->maxLength(180)->rules())
            ->toBe(['required', 'string', 'max:180'])
            ->and(TextareaProperty::make('description')->rules())
            ->toBe(['nullable', 'string'])
            ->and(UrlProperty::make('url')->required()->rules())
            ->toBe(['required', 'string', 'url:http,https']);
    });

    it('rejects invalid property keys and maximum lengths', function (): void {
        expect(fn () => TextProperty::make('Display URL'))
            ->toThrow(InvalidPropertyConfiguration::class)
            ->and(fn () => TextProperty::make('title')->maxLength(0))
            ->toThrow(InvalidPropertyConfiguration::class);
    });
});
