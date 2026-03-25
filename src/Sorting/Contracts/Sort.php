<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Sorting\Contracts;

use Illuminate\Database\Eloquent\Builder;

interface Sort
{
    public function key(): string;

    public function apply(Builder $query, string $direction): void;
}
