<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Filters;

use Illuminate\Database\Eloquent\Builder;

class WhereDoesntHave extends WhereHas
{
    public function apply(Builder $query, mixed $value): void
    {
        $filters = $this->resolveFilters($value);

        $query->whereDoesntHave(
            $this->relationshipName(),
            fn (Builder $subQuery) => $this->applyResolvedFilters($subQuery, $filters),
        );
    }
}
