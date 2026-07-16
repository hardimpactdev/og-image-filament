<?php

declare(strict_types=1);

namespace HardImpact\OgImageFilament\Properties;

enum PropertyType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Url = 'url';

    public function make(string $key): Property
    {
        return match ($this) {
            self::Text => TextProperty::make($key),
            self::Textarea => TextareaProperty::make($key),
            self::Url => UrlProperty::make($key),
        };
    }
}
