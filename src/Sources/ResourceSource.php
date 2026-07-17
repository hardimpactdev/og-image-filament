<?php

declare(strict_types=1);

namespace HardImpact\OgImageFilament\Sources;

use Closure;
use Filament\Resources\Resource;
use HardImpact\OgImageFilament\Exceptions\InvalidSourceConfiguration;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Stringable;

final class ResourceSource
{
    private ?string $configuredLabel = null;

    private ?string $configuredTemplate = null;

    /** @var ?array<int, string> */
    private ?array $configuredSearchColumns = null;

    private ?Closure $recordTitleCallback = null;

    private ?Closure $queryCallback = null;

    private ?Closure $dataResolver = null;

    private ?Closure $pathResolver = null;

    /** @param class-string<resource> $resource */
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

    public function template(string $view): self
    {
        $view = trim($view);

        if ($view === '') {
            throw InvalidSourceConfiguration::invalidTemplate($this->resource);
        }

        $this->configuredTemplate = $view;

        return $this;
    }

    public function dataUsing(Closure $resolver): self
    {
        $this->dataResolver = $resolver;

        return $this;
    }

    /** @param array<int, string> $columns */
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

    public function pathUsing(Closure $resolver): self
    {
        $this->pathResolver = $resolver;

        return $this;
    }

    public function assertConfigured(): void
    {
        $this->getTemplate();

        if ($this->dataResolver === null) {
            throw InvalidSourceConfiguration::missingDataResolver($this->resource);
        }

        if ($this->pathResolver === null) {
            throw InvalidSourceConfiguration::missingPathResolver($this->resource);
        }
    }

    public function getKey(): string
    {
        return $this->resource;
    }

    public function getLabel(): string
    {
        return $this->configuredLabel ?? $this->resource::getPluralModelLabel();
    }

    public function getTemplate(): string
    {
        return $this->configuredTemplate
            ?? throw InvalidSourceConfiguration::missingTemplate($this->resource);
    }

    public function resolveData(Model $record): object
    {
        $this->ensureValidRecord($record);

        if ($this->dataResolver === null) {
            throw InvalidSourceConfiguration::missingDataResolver($this->resource);
        }

        $data = ($this->dataResolver)($record);

        if (! is_object($data)) {
            throw InvalidSourceConfiguration::invalidData($this->resource, $data);
        }

        return $data;
    }

    public function isAccessible(): bool
    {
        return $this->resource::canAccess();
    }

    /** @return array<int|string, string> */
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

        $record = $this->resolveRecordForGeneration($key);

        if ($record === null || ! $this->resource::canView($record)) {
            return null;
        }

        return $record;
    }

    public function resolveRecordForGeneration(int|string $key): ?Model
    {
        return $this->resource::resolveRecordRouteBinding(
            $key,
            function (Builder $query): Builder {
                $this->applyQueryCallback($query);

                return $query;
            },
        );
    }

    public function resolvePath(Model $record): string
    {
        $this->ensureValidRecord($record);

        if ($this->pathResolver === null) {
            throw InvalidSourceConfiguration::missingPathResolver($this->resource);
        }

        $path = ($this->pathResolver)($record);

        if (
            ! is_string($path)
            || trim($path) === ''
            || str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || str_contains($path, '..')
        ) {
            throw InvalidSourceConfiguration::invalidPath($this->resource, $path);
        }

        return trim($path);
    }

    public function getRecordTitle(Model $record): string
    {
        $this->ensureValidRecord($record);

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

    /** @return array<int, string> */
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

    /** @param Builder<Model> $query */
    private function applyQueryCallback(Builder $query): void
    {
        if ($this->queryCallback !== null) {
            ($this->queryCallback)($query);
        }
    }

    private function ensureValidRecord(Model $record): void
    {
        $model = $this->resource::getModel();

        if (! $record instanceof $model) {
            throw InvalidSourceConfiguration::invalidRecord($this->resource, $record);
        }
    }
}
