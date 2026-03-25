<?php

declare(strict_types=1);

namespace Zakobo\JsonApiQuery\Tests\Fixtures\Filters;

use Illuminate\Database\Eloquent\Builder;
use Zakobo\JsonApiQuery\Filters\Contracts\Filter;

class TitleMatchesFilter implements Filter
{
    public function __construct(
        protected readonly string $key,
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function apply(Builder $query, mixed $value): void
    {
        $query->where($query->getModel()->qualifyColumn('title'), '=', $value);
    }
}
