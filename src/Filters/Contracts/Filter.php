<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Filters\Contracts;

use Illuminate\Database\Eloquent\Builder;

interface Filter
{
    public function key(): string;

    public function apply(Builder $query, mixed $value): void;
}
