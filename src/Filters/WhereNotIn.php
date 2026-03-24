<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Filters;

use Illuminate\Database\Eloquent\Builder;

class WhereNotIn extends WhereIn
{
    public function apply(Builder $query, mixed $value): void
    {
        $column = $query->getModel()->qualifyColumn($this->column ?? $this->key);

        $query->whereNotIn($column, $this->resolveValues($value));
    }
}
