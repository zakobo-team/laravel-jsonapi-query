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

        foreach ($filters as $key => $value) {
            $this->dispatchFilter($query, $key, $value);
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
        }
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
