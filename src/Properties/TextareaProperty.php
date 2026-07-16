<?php

declare(strict_types=1);

namespace HardImpact\OgImageFilament\Properties;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\Textarea;

final class TextareaProperty extends Property
{
    public function formComponent(): Field
    {
        return $this->configure(
            Textarea::make($this->key)
                ->maxLength($this->maximumLength),
        );
    }
}
