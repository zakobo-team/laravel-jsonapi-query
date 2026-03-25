<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Zakobo\JsonApiQuery\Filters\Contracts\Filter;

class FilterDispatcher
{
    protected const OPERATOR_KEYS = ['gt', 'gte', 'lt', 'lte', 'eq'];

    /** @var array<string> */
    protected array $attributes = [];

    /** @var array<string> */
    protected array $relationships = [];

    /** @var array<string, Filter> */
    protected array $additionalFilters = [];

    protected function __construct()
    {
    }

    public static function make(): static
    {
        return new static;
    }

    /** @param array<string> $attributes */
    public function attributes(array $attributes): static
    {
        $this->attributes = $attributes;

        return $this;
    }

    /** @param array<string> $relationships */
    public function relationships(array $relationships): static
    {
        $this->relationships = $relationships;

        return $this;
    }

    /** @param array<Filter> $filters */
    public function additionalFilters(array $filters): static
    {
        foreach ($filters as $filter) {
            $this->additionalFilters[$filter->key()] = $filter;
        }

        return $this;
    }

    public function apply(Builder $query, Request $request): void
    {
        $filters = $request->query('filter');

        if (! is_array($filters)) {
            return;
        }

        [$normalizedFilters, $deferredRelationshipFilters] = $this->normalizeFilters($filters);

        foreach ($normalizedFilters as $key => $value) {
            $this->dispatchFilter($query, $key, $value);
        }

        foreach ($deferredRelationshipFilters as $relationship => $value) {
            $this->applyRelationshipFilter($query, $relationship, $value);
        }
    }

    protected function dispatchFilter(Builder $query, string $key, mixed $value): void
    {
        if (in_array($key, $this->attributes, true)) {
            $this->applyAttributeFilter($query, $key, $value);

            return;
        }

        if (in_array($key, $this->relationships, true)) {
            $this->applyRelationshipFilter($query, $key, $value);

            return;
        }

        if (isset($this->additionalFilters[$key])) {
            $this->additionalFilters[$key]->apply($query, $value);

            return;
        }

    }

    /**
     * Normalize dotted relationship filters so predicates targeting the same
     * relationship are applied within a single whereHas subquery.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: array<string, mixed>, 1: array<string, array<string, mixed>>}
     */
    protected function normalizeFilters(array $filters): array
    {
        $normalizedFilters = [];
        $normalizedRelationshipFilters = [];
        $deferredRelationshipFilters = [];

        foreach ($filters as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if ($this->isDottedRelationshipFilter($key)) {
                [$relationship, $nestedValue] = $this->normalizeDottedRelationshipFilter($key, $value);

                $existing = $normalizedRelationshipFilters[$relationship] ?? [];
                $normalizedRelationshipFilters[$relationship] = $this->mergeFilterValues($existing, $nestedValue);

                continue;
            }

            $normalizedFilters[$key] = $value;
        }

        foreach ($normalizedRelationshipFilters as $relationship => $filters) {
            if (! array_key_exists($relationship, $normalizedFilters)) {
                $normalizedFilters[$relationship] = $filters;

                continue;
            }

            if (is_array($normalizedFilters[$relationship])) {
                $normalizedFilters[$relationship] = $this->mergeFilterValues($normalizedFilters[$relationship], $filters);

                continue;
            }

            $deferredRelationshipFilters[$relationship] = $filters;
        }

        return [$normalizedFilters, $deferredRelationshipFilters];
    }

    protected function isDottedRelationshipFilter(string $key): bool
    {
        if (! str_contains($key, '.')) {
            return false;
        }

        $segments = explode('.', $key, 2);
        $relationship = $segments[0];

        return in_array($relationship, $this->relationships, true);
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    protected function normalizeDottedRelationshipFilter(string $key, mixed $value): array
    {
        [$relationship, $remainingPath] = explode('.', $key, 2);

        return [$relationship, $this->buildNestedValue($remainingPath, $value)];
    }

    protected function buildNestedValue(string $path, mixed $value): array
    {
        if (! str_contains($path, '.')) {
            return [$path => $value];
        }

        $segments = explode('.', $path, 2);

        return [$segments[0] => $this->buildNestedValue($segments[1], $value)];
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    protected function mergeFilterValues(array $existing, array $incoming): array
    {
        foreach ($incoming as $key => $value) {
            if (
                isset($existing[$key])
                && is_array($existing[$key])
                && is_array($value)
            ) {
                $existing[$key] = $this->mergeFilterValues($existing[$key], $value);

                continue;
            }

            $existing[$key] = $value;
        }

        return $existing;
    }

    protected function applyAttributeFilter(Builder $query, string $key, mixed $value): void
    {
        if (is_array($value) && $this->containsOperatorKeys($value)) {
            foreach ($value as $operator => $operatorValue) {
                if (in_array($operator, self::OPERATOR_KEYS, true)) {
                    $filter = Where::make($key);
                    $filter = match ($operator) {
                        'gt' => $filter->gt(),
                        'gte' => $filter->gte(),
                        'lt' => $filter->lt(),
                        'lte' => $filter->lte(),
                        'eq' => $filter,
                    };
                    $filter->apply($query, $operatorValue);
                }
            }

            return;
        }

        Where::make($key)->apply($query, $value);
    }

    protected function applyRelationshipFilter(Builder $query, string $key, mixed $value): void
    {
        if (is_array($value)) {
            WhereHas::make($key)->apply($query, $value);

            return;
        }

        Has::make($key)->apply($query, $value);
    }

    protected function containsOperatorKeys(array $value): bool
    {
        foreach (array_keys($value) as $key) {
            if (in_array($key, self::OPERATOR_KEYS, true)) {
                return true;
            }
        }

        return false;
    }
}
