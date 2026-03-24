<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Filters\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

interface PivotFilter
{
    public function key(): string;

    public function applyToRelation(BelongsToMany $relation, mixed $value): void;
}
