<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Fixtures\Sorting;

use Illuminate\Database\Eloquent\Builder;
use Zakobo\JsonApiQuery\Sorting\Contracts\Sort;

class TitleLengthSort implements Sort
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
        $query->orderByRaw('LENGTH(title) '.strtoupper($direction));
    }
}
