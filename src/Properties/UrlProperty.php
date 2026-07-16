<?php

declare(strict_types=1);

namespace HardImpact\OgImageFilament\Properties;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;

final class UrlProperty extends Property
{
    /**
     * @return array<int, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'url:http,https',
        ];
    }

    public function formComponent(): Field
    {
        return $this->configure(
            TextInput::make($this->key)
                ->url()
                ->maxLength($this->maximumLength)
                ->rules(['url:http,https']),
        );
    }
}
