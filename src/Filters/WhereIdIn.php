<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Filters;

use Illuminate\Database\Eloquent\Builder;

class WhereIdIn extends WhereIn
{
    public function apply(Builder $query, mixed $value): void
    {
        $column = $query->getModel()->qualifyColumn($query->getModel()->getRouteKeyName());

        $query->whereIn($column, $this->resolveValues($value));
    }
}
