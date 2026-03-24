<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Filters;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WherePivotNotIn extends WherePivotIn
{
    public function applyToRelation(BelongsToMany $relation, mixed $value): void
    {
        $relation->wherePivotNotIn($this->column ?? $this->key, $this->resolveValues($value));
    }
}
