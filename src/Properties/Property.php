<?php

declare(strict_types=1);

namespace HardImpact\OgImageFilament\Properties;

use Filament\Forms\Components\Field;
use HardImpact\OgImageFilament\Exceptions\InvalidPropertyConfiguration;
use Illuminate\Support\Str;

abstract class Property
{
    protected string $label;

    protected bool $isRequired = false;

    protected ?int $maximumLength = null;

    final protected function __construct(public readonly string $key)
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $key) !== 1) {
            throw InvalidPropertyConfiguration::invalidKey($key);
        }

        $this->label = Str::headline($key);
    }

    public static function make(string $key): static
    {
        return new static($key);
    }

    public function label(string $label): static
    {
        $label = trim($label);

        if ($label === '') {
            throw InvalidPropertyConfiguration::emptyLabel($this->key);
        }

        $this->label = $label;

        return $this;
    }

    public function required(bool $condition = true): static
    {
        $this->isRequired = $condition;

        return $this;
    }

    public function maxLength(?int $length): static
    {
        if ($length !== null && $length < 1) {
            throw InvalidPropertyConfiguration::invalidMaximumLength($this->key, $length);
        }

        $this->maximumLength = $length;

        return $this;
    }

    public function isRequired(): bool
    {
        return $this->isRequired;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getMaximumLength(): ?int
    {
        return $this->maximumLength;
    }

    abstract public function getType(): PropertyType;

    /**
     * @return array<int, mixed>
     */
    public function rules(): array
    {
        $rules = [
            $this->isRequired ? 'required' : 'nullable',
            'string',
        ];

        if ($this->maximumLength !== null) {
            $rules[] = "max:{$this->maximumLength}";
        }

        return $rules;
    }

    abstract public function formComponent(): Field;

    protected function configure(Field $field): Field
    {
        return $field
            ->label($this->label)
            ->required($this->isRequired)
            ->live(debounce: 300);
    }
}
