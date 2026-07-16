<?php

declare(strict_types=1);

use HardImpact\OgImageFilament\Exceptions\InvalidPropertyConfiguration;
use HardImpact\OgImageFilament\Properties\PropertyFactory;
use HardImpact\OgImageFilament\Properties\TextareaProperty;
use HardImpact\OgImageFilament\Properties\TextProperty;
use HardImpact\OgImageFilament\Properties\UrlProperty;

describe('property definitions', function (): void {
    it('round trips the supported property types', function (): void {
        $properties = [
            TextProperty::make('label')
                ->label('Eyebrow')
                ->required()
                ->maxLength(40),
            TextareaProperty::make('description')
                ->label('Summary')
                ->maxLength(160),
            UrlProperty::make('url')
                ->label('Public URL')
                ->required(),
        ];

        $definitions = PropertyFactory::definitions($properties);
        $restored = PropertyFactory::fromDefinitions($definitions);

        expect($definitions)->toBe([
            [
                'key' => 'label',
                'label' => 'Eyebrow',
                'type' => 'text',
                'required' => true,
                'max_length' => 40,
            ],
            [
                'key' => 'description',
                'label' => 'Summary',
                'type' => 'textarea',
                'required' => false,
                'max_length' => 160,
            ],
            [
                'key' => 'url',
                'label' => 'Public URL',
                'type' => 'url',
                'required' => true,
                'max_length' => null,
            ],
        ])
            ->and($restored['label'])->toBeInstanceOf(TextProperty::class)
            ->and($restored['description'])->toBeInstanceOf(TextareaProperty::class)
            ->and($restored['url'])->toBeInstanceOf(UrlProperty::class)
            ->and(PropertyFactory::definitions(array_values($restored)))->toBe($definitions);
    });

    it('rejects invalid definitions', function (array $definitions): void {
        expect(fn () => PropertyFactory::fromDefinitions($definitions))
            ->toThrow(InvalidPropertyConfiguration::class);
    })->with([
        'unknown type' => [[[
            'key' => 'title',
            'label' => 'Title',
            'type' => 'markdown',
            'required' => false,
            'max_length' => null,
        ]]],
        'duplicate key' => [[
            [
                'key' => 'title',
                'label' => 'Title',
                'type' => 'text',
                'required' => false,
                'max_length' => null,
            ],
            [
                'key' => 'title',
                'label' => 'Another title',
                'type' => 'textarea',
                'required' => false,
                'max_length' => null,
            ],
        ]],
        'invalid key' => [[[
            'key' => 'Display title',
            'label' => 'Title',
            'type' => 'text',
            'required' => false,
            'max_length' => null,
        ]]],
        'empty label' => [[[
            'key' => 'title',
            'label' => ' ',
            'type' => 'text',
            'required' => false,
            'max_length' => null,
        ]]],
        'invalid maximum length' => [[[
            'key' => 'title',
            'label' => 'Title',
            'type' => 'text',
            'required' => false,
            'max_length' => 0,
        ]]],
    ]);
});
