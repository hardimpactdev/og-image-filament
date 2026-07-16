<?php

declare(strict_types=1);

use HardImpact\OgImageFilament\Exceptions\InvalidMappedProperties;
use HardImpact\OgImageFilament\Exceptions\UnknownProperty;
use HardImpact\OgImageFilament\Properties\TextareaProperty;
use HardImpact\OgImageFilament\Properties\TextProperty;
use HardImpact\OgImageFilament\Properties\UrlProperty;
use HardImpact\OgImageFilament\PropertyBag;

it('normalizes mapped string values and optional omissions', function (): void {
    $bag = PropertyBag::fromMapping(
        properties: [
            TextProperty::make('title')->required(),
            TextareaProperty::make('description'),
        ],
        values: ['title' => str('A mapped title')],
    );

    expect($bag->string('title'))->toBe('A mapped title')
        ->and($bag->get('description'))->toBeNull()
        ->and($bag->filled('description'))->toBeFalse()
        ->and($bag->has('description'))->toBeTrue()
        ->and($bag->all())->toBe([
            'title' => 'A mapped title',
            'description' => null,
        ]);
});

it('rejects unknown, missing required, and non-string mapped values', function (): void {
    $properties = [TextProperty::make('title')->required()];

    expect(fn () => PropertyBag::fromMapping($properties, ['unknown' => 'value']))
        ->toThrow(InvalidMappedProperties::class)
        ->and(fn () => PropertyBag::fromMapping($properties, []))
        ->toThrow(InvalidMappedProperties::class)
        ->and(fn () => PropertyBag::fromMapping($properties, ['title' => 42]))
        ->toThrow(InvalidMappedProperties::class);
});

it('rejects duplicate definitions and mapped values that fail validation', function (): void {
    expect(fn () => PropertyBag::fromMapping(
        [TextProperty::make('title'), TextProperty::make('title')],
        ['title' => 'Hello'],
    ))->toThrow(InvalidMappedProperties::class)
        ->and(fn () => PropertyBag::fromMapping(
            [UrlProperty::make('url')],
            ['url' => 'not-a-url'],
        ))->toThrow(InvalidMappedProperties::class);
});

it('allows incomplete state while preserving the configured contract', function (): void {
    $bag = PropertyBag::fromState(
        [
            TextProperty::make('title')->required(),
            UrlProperty::make('url')->required(),
        ],
        [
            'title' => '',
            'url' => 'not-yet-a-url',
            'unknown' => 'ignored',
        ],
    );

    expect($bag->get('title'))->toBe('')
        ->and($bag->get('url'))->toBe('not-yet-a-url')
        ->and($bag->has('unknown'))->toBeFalse();
});

it('throws when a template reads an unconfigured property', function (): void {
    $bag = PropertyBag::fromState([TextProperty::make('title')], ['title' => 'Hello']);

    expect(fn () => $bag->string('subtitle'))
        ->toThrow(UnknownProperty::class);
});
