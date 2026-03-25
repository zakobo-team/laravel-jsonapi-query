<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Sorting;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Zakobo\JsonApiQuery\Sorting\Contracts\Sort;

class ScopeSort implements Sort
{
    public function __construct(
        protected readonly string $key,
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function apply(Builder $query, string $direction): void
    {
        $scopeName = 'orderBy'.Str::studly($this->key);

        $query->{$scopeName}($direction);
    }
}
