<?php

declare(strict_types=1);

namespace HardImpact\OgImageFilament\Sources;

use Closure;
use HardImpact\OgImageFilament\Exceptions\InvalidSourceConfiguration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class ModelValue
{
    private ?string $configuredLabel = null;

    private ?Closure $resolver = null;

    private function __construct(public readonly string $key) {}

    public static function make(string $key): self
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $key) !== 1) {
            throw InvalidSourceConfiguration::invalidModelValueKey($key);
        }

        return new self($key);
    }

    public function label(string $label): self
    {
        if (trim($label) === '') {
            throw InvalidSourceConfiguration::invalidModelValueLabel($this->key);
        }

        $this->configuredLabel = trim($label);

        return $this;
    }

    public function resolveUsing(Closure $resolver): self
    {
        $this->resolver = $resolver;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->configuredLabel ?? Str::headline($this->key);
    }

    public function resolve(Model $record): mixed
    {
        if ($this->resolver === null) {
            throw InvalidSourceConfiguration::missingModelValueResolver($this->key);
        }

        return ($this->resolver)($record);
    }
}
