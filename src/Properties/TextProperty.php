<?php

declare(strict_types=1);

namespace HardImpact\OgImageFilament\Properties;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;

final class TextProperty extends Property
{
    public function formComponent(): Field
    {
        return $this->configure(
            TextInput::make($this->key)
                ->maxLength($this->maximumLength),
        );
    }
}
