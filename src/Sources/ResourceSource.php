<?php

declare(strict_types=1);

namespace HardImpact\OgImageFilament\Sources;

use Closure;
use Filament\Resources\Resource;
use HardImpact\OgImageFilament\Exceptions\InvalidSourceConfiguration;
use HardImpact\OgImageFilament\Settings\MappingSource;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Stringable;

final class ResourceSource
{
    private ?string $configuredLabel = null;

    /** @var ?array<int, string> */
    private ?array $configuredSearchColumns = null;

    private ?Closure $recordTitleCallback = null;

    private ?Closure $queryCallback = null;

    private ?Closure $mapper = null;

    /** @var array<string, array{source: string, value: string}> */
    private array $configuredDefaultMappings = [];

    /**
     * @param  class-string<resource>  $resource
     */
    private function __construct(public readonly string $resource) {}

    public static function make(string $resource): self
    {
        if (! is_subclass_of($resource, Resource::class)) {
            throw InvalidSourceConfiguration::invalidResource($resource);
        }

        return new self($resource);
    }

    public function label(string $label): self
    {
        $this->configuredLabel = $label;

        return $this;
    }

    /**
     * @param  array<int, string>  $columns
     */
    public function searchColumns(array $columns): self
    {
        $columns = array_values(array_filter(
            $columns,
            fn (string $column): bool => trim($column) !== '',
        ));

        if ($columns === []) {
            throw InvalidSourceConfiguration::invalidSearchColumns($this->resource);
        }

        $this->configuredSearchColumns = array_values(array_unique($columns));

        return $this;
    }

    public function recordTitle(Closure $callback): self
    {
        $this->recordTitleCallback = $callback;

        return $this;
    }

    public function modifyQueryUsing(Closure $callback): self
    {
        $this->queryCallback = $callback;

        return $this;
    }

    public function map(Closure $callback): self
    {
        $this->mapper = $callback;

        return $this;
    }

    public function hasMapper(): bool
    {
        return $this->mapper !== null;
    }

    /**
     * @param  array<array-key, mixed>  $mappings
     */
    public function defaultMappings(array $mappings): self
    {
        $normalizedMappings = [];

        foreach ($mappings as $property => $mapping) {
            if (
                ! is_string($property)
                || ! is_array($mapping)
                || ! is_string($mapping['source'] ?? null)
                || ! is_string($mapping['value'] ?? null)
            ) {
                throw InvalidSourceConfiguration::invalidMapping($this->resource, (string) $property);
            }

            $source = MappingSource::tryFrom($mapping['source']);

            if ($source === null) {
                throw InvalidSourceConfiguration::invalidMapping($this->resource, $property);
            }

            $value = $mapping['value'];

            $normalizedMappings[$property] = [
                'source' => $source->value,
                'value' => $value,
            ];
        }

        $this->configuredDefaultMappings = $normalizedMappings;

        return $this;
    }

    public function getKey(): string
    {
        return $this->resource;
    }

    public function getLabel(): string
    {
        return $this->configuredLabel ?? $this->resource::getPluralModelLabel();
    }

    /**
     * @return array<string, array{source: string, value: string}>
     */
    public function getDefaultMappings(): array
    {
        return $this->configuredDefaultMappings;
    }

    /**
     * @return array<string, string>
     */
    public function getModelColumns(): array
    {
        $modelClass = $this->resource::getModel();
        $model = new $modelClass;
        $columns = $model->getConnection()
            ->getSchemaBuilder()
            ->getColumnListing($model->getTable());

        return array_combine(
            $columns,
            array_map(
                fn (string $column): string => Str::headline($column),
                $columns,
            ),
        );
    }

    public function isAccessible(): bool
    {
        return $this->resource::canAccess();
    }

    /**
     * @return array<int|string, string>
     */
    public function search(?string $search, int $limit = 50): array
    {
        if (! $this->isAccessible() || $limit < 1) {
            return [];
        }

        $query = $this->resource::getEloquentQuery();
        $this->applyQueryCallback($query);

        if (filled($search)) {
            $columns = $this->getSearchColumns();

            $query->where(function (Builder $query) use ($columns, $search): void {
                foreach ($columns as $index => $column) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $query->{$method}($column, 'like', "%{$search}%");
                }
            });
        }

        $options = [];
        $limit = min($limit, 50);
        $batchSize = max($limit, 50);
        $offset = 0;

        do {
            $records = (clone $query)
                ->offset($offset)
                ->limit($batchSize)
                ->get();

            foreach ($records as $record) {
                if (! $this->resource::canView($record)) {
                    continue;
                }

                $key = $record->getRouteKey();

                if (! is_int($key) && ! is_string($key)) {
                    continue;
                }

                $options[$key] = $this->getRecordTitle($record);

                if (count($options) >= $limit) {
                    return $options;
                }
            }

            $offset += $records->count();
        } while ($records->count() === $batchSize);

        return $options;
    }

    public function resolveRecord(int|string $key): ?Model
    {
        if (! $this->isAccessible()) {
            return null;
        }

        $record = $this->resource::resolveRecordRouteBinding(
            $key,
            function (Builder $query): Builder {
                $this->applyQueryCallback($query);

                return $query;
            },
        );

        if ($record === null || ! $this->resource::canView($record)) {
            return null;
        }

        return $record;
    }

    public function getRecordTitle(Model $record): string
    {
        $model = $this->resource::getModel();

        if (! $record instanceof $model) {
            throw InvalidSourceConfiguration::invalidRecord($this->resource, $record);
        }

        $title = $this->recordTitleCallback === null
            ? $this->resource::getRecordTitle($record)
            : ($this->recordTitleCallback)($record);

        if ($title instanceof Htmlable) {
            return trim(html_entity_decode(strip_tags($title->toHtml()), ENT_QUOTES | ENT_HTML5));
        }

        if (is_string($title) || $title instanceof Stringable) {
            return (string) $title;
        }

        throw InvalidSourceConfiguration::invalidRecordTitle($this->resource, $title);
    }

    /**
     * @return array<string, mixed>
     */
    public function mapRecord(Model $record): array
    {
        if ($this->mapper === null) {
            throw InvalidSourceConfiguration::missingMapper($this->resource);
        }

        $model = $this->resource::getModel();

        if (! $record instanceof $model) {
            throw InvalidSourceConfiguration::invalidRecord($this->resource, $record);
        }

        $values = ($this->mapper)($record);

        if (! is_array($values)) {
            throw InvalidSourceConfiguration::invalidMappedResult($this->resource);
        }

        $mappedValues = [];

        foreach ($values as $key => $value) {
            if (! is_string($key)) {
                throw InvalidSourceConfiguration::invalidMappedResult($this->resource);
            }

            $mappedValues[$key] = $value;
        }

        return $mappedValues;
    }

    /**
     * @return array<int, string>
     */
    private function getSearchColumns(): array
    {
        if ($this->configuredSearchColumns !== null) {
            return $this->configuredSearchColumns;
        }

        $columns = $this->resource::getGloballySearchableAttributes();
        $recordTitleAttribute = $this->resource::getRecordTitleAttribute();

        if ($recordTitleAttribute !== null) {
            $columns[] = $recordTitleAttribute;
        }

        $columns = array_values(array_unique(array_filter(
            $columns,
            fn (string $column): bool => trim($column) !== '',
        )));

        if ($columns === []) {
            throw InvalidSourceConfiguration::missingSearchColumns($this->resource);
        }

        return $columns;
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function applyQueryCallback(Builder $query): void
    {
        if ($this->queryCallback !== null) {
            ($this->queryCallback)($query);
        }
    }
}
