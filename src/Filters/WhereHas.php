<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Zakobo\JsonApiQuery\Filters\Contracts\Filter;

class WhereHas implements Filter
{
    protected const OPERATOR_KEYS = ['gt', 'gte', 'lt', 'lte', 'eq'];

    /** @var array<Filter> */
    protected array $subFilters = [];

    protected function __construct(
        protected readonly string $key,
        protected readonly ?string $relationship = null,
    ) {
    }

    public static function make(string $key, ?string $relationship = null): static
    {
        return new static($key, $relationship);
    }

    public function key(): string
    {
        return $this->key;
    }

    /** @param array<Filter> $filters */
    public function withFilters(array $filters): static
    {
        $this->subFilters = $filters;

        return $this;
    }

    protected function relationshipName(): string
    {
        return $this->relationship ?? $this->key;
    }

    public function apply(Builder $query, mixed $value): void
    {
        $filters = $this->resolveFilters($value);

        $query->whereHas(
            $this->relationshipName(),
            fn (Builder $subQuery) => $this->applyResolvedFilters($subQuery, $filters),
        );
    }

    protected function resolveFilters(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    protected function findFilter(string $key): ?Filter
    {
        foreach ($this->subFilters as $filter) {
            if ($filter->key() === $key) {
                return $filter;
            }
        }

        return null;
    }

    protected function applyResolvedFilters(Builder $query, array $filters): void
    {
        foreach ($filters as $key => $filterValue) {
            if (! is_string($key)) {
                continue;
            }

            $filter = $this->findFilter($key);

            if ($filter !== null) {
                $filter->apply($query, $filterValue);

                continue;
            }

            $this->applyDynamicFilter($query, $key, $filterValue);
        }
    }

    protected function applyDynamicFilter(Builder $query, string $key, mixed $value): void
    {
        if (is_array($value)) {
            if ($this->containsOperatorKeys($value)) {
                $this->applyOperatorFilters($query, $key, $value);

                return;
            }

            if ($this->isRelationship($query, $key)) {
                self::make($key)->apply($query, $value);
            }

            return;
        }

        if ($this->isRelationship($query, $key)) {
            Has::make($key)->apply($query, $value);

            return;
        }

        Where::make($key)->apply($query, $value);
    }

    protected function applyOperatorFilters(Builder $query, string $key, array $value): void
    {
        foreach ($value as $operator => $operatorValue) {
            if (! in_array($operator, self::OPERATOR_KEYS, true)) {
                continue;
            }

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

    protected function containsOperatorKeys(array $value): bool
    {
        foreach (array_keys($value) as $key) {
            if (in_array($key, self::OPERATOR_KEYS, true)) {
                return true;
            }
        }

        return false;
    }

    protected function isRelationship(Builder $query, string $key): bool
    {
        $model = $query->getModel();

        if (! method_exists($model, $key)) {
            return false;
        }

        $relation = Relation::noConstraints(fn () => $model->{$key}());

        return $relation instanceof Relation;
    }
}
